<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\oe_authorisation_syncope\Exception\SyncopeGroupNotFoundException;
use Drupal\oe_authorisation_syncope\Exception\SyncopeUserException;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;

/**
 * Handles the mapping of roles with Syncope.
 */
class SyncopeRoleMapper {

  /**
   * The Syncope client.
   *
   * @var \Drupal\oe_authorisation_syncope\SyncopeClient
   */
  protected $client;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The State API.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * SyncopeRoleMapper constructor.
   *
   * @param \Drupal\oe_authorisation_syncope\SyncopeClient $client
   *   The Syncope client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State API.
   */
  public function __construct(SyncopeClient $client, EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerChannelFactory, StateInterface $state) {
    $this->client = $client;
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerChannelFactory->get('oe_authorisation_syncope');
    $this->state = $state;
  }

  /**
   * Acts on the "entity_presave" hook of the Role entity.
   *
   * If the role is not correctly kept in sync in Syncope, we throw an exception
   * to prevent Drupal from finishing the process.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The user role.
   */
  public function preSave(RoleInterface $role): void {
    if (!$this->client::isEnabled()) {
      return;
    }

    // We do not map global roles as they are created during provisioning or
    // directly in the Syncope service.
    if ($role->getThirdPartySetting('oe_authorisation', 'global', FALSE)) {
      return;
    }

    // We also don't want to ever map the default roles.
    if (\in_array($role->id(), ['anonymous', 'authenticated'])) {
      return;
    }

    if (!$role->isNew()) {
      $this->mapExistingRole($role);
      return;
    }

    $this->mapNewRole($role);
  }

  /**
   * Acts on the "entity_predelete" hook of the Role entity.
   *
   * If the role could not be deleted in Syncope, we throw an exception to
   * prevent the delete from happening in Drupal.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The user role.
   */
  public function preDelete(RoleInterface $role): void {
    if (!$this->client::isEnabled()) {
      return;
    }

    $uuid = $this->getRoleUuid($role);
    if (!$uuid) {
      $this->logger->info('A role was deleted but had no UUID to delete its Syncope counterpart: ' . $role->id());
      return;
    }

    $this->client->deleteGroup($uuid);
    $this->state->delete('oe_authorisation_syncope.role_uuid.' . $role->id());
  }

  /**
   * Returns the usable roles that can be synced with Syncope.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user.
   *
   * @return array
   *   The roles.
   */
  public function getRolesForUser(UserInterface $user): array {
    $user_roles = $user->getRoles();
    $roles = [];
    $role_entities = $this->entityTypeManager->getStorage('user_role')->loadByProperties(['id' => $user_roles]);

    /** @var \Drupal\user\RoleInterface $role */
    foreach ($role_entities as $role) {
      $group_id = $this->getRoleUuid($role);
      if (!$group_id) {
        continue;
      }

      $roles[] = $group_id;
    }

    return $roles;
  }

  /**
   * Returns the roles of a user from those found in Syncope.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user.
   *
   * @return array
   *   The Drupal roles of this user mapped from Syncope.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeUserException
   */
  public function getUserDrupalRolesFromSyncope(UserInterface $user): array {
    $uuid = $user->get('syncope_uuid')->value;
    if (!$uuid) {
      throw new SyncopeUserException('The user does not have a Syncope mapping.');
    }

    // We don't catch this exception because we want a failure.
    $roles = ['authenticated'];

    $groups = $this->client->getAllUserGroups($user->label());

    if (!empty($groups)) {
      $ids = [];
      foreach ($groups as $group) {
        $ids[] = $group->getDrupalName();
      }

      if ($ids) {
        $roles = array_merge($roles, array_values($ids));
      }
    }

    return $roles;
  }

  /**
   * Returns the Syncope UUID for a given role into the State API.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The role.
   *
   * @return string|null
   *   The UUID.
   */
  public function getRoleUuid(RoleInterface $role):? string {
    return $this->state->get('oe_authorisation_syncope.role_uuid.' . $role->id());
  }

  /**
   * Sets the Syncope UUID for a given role into the State API.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The Role.
   * @param string $uuid
   *   The UUID.
   */
  public function setRoleUuid(RoleInterface $role, string $uuid): void {
    $this->state->set('oe_authorisation_syncope.role_uuid.' . $role->id(), $uuid);
  }

  /**
   * Maps a new role when it first gets created.
   *
   * We allow the exception to be thrown to prevent the role from being
   * created if something fails.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The user role.
   */
  protected function mapNewRole(RoleInterface $role): void {
    try {
      $id = $this->getRoleUuid($role);
      if ($id) {
        $group = $this->client->getGroup($id);
        // If found, we do nothing in Syncope but we do set the Group ID.
        $this->setRoleUuid($role, $group->getUuid());
        return;
      }

      // If the ID is NULL, we try by name. Normally this should not be needed
      // but just in case the role already exists on the Syncope instance.
      $group = $this->client->getGroup($role->id(), SyncopeClient::IDENTIFIER_NAME);
      // Just in case the group is already there but no UUID has been set.
      $this->setRoleUuid($role, $group->getUuid());
    }
    catch (SyncopeGroupNotFoundException $e) {
      $group = $this->client->createGroup($role->id());
      $this->setRoleUuid($role, $group->getUuid());
    }
  }

  /**
   * Maps an existing role.
   *
   * This is basically only needed to map an existing role that doesn't already
   * exist in Syncope.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The Drupal role.
   */
  protected function mapExistingRole(RoleInterface $role): void {
    // We can just defer to mapNewRole() as it does the check for existing role.
    $this->mapNewRole($role);
  }

}
