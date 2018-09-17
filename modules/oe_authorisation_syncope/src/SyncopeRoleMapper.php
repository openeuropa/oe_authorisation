<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
   * SyncopeRoleMapper constructor.
   *
   * @param \Drupal\oe_authorisation_syncope\SyncopeClient $client
   *   The Syncope client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   */
  public function __construct(SyncopeClient $client, EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->client = $client;
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerChannelFactory->get('oe_authorisation_syncope');
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
    $uuid = $role->getThirdPartySetting('oe_authorisation_syncope', 'syncope_group');
    if (!$uuid) {
      $this->logger->info('A role was deleted but had no UUID to delete its Syncope counterpart: ' . $role->id());
      return;
    }

    $this->client->deleteGroup($uuid);
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
      $group_id = $role->getThirdPartySetting('oe_authorisation_syncope', 'syncope_group', NULL);
      if (!$group_id) {
        continue;
      }

      $roles[] = $group_id;
    }

    return $roles;
  }

  /**
   * Updates the roles of a user from those found in Syncope.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user.
   *
   * @return array
   *   The Drupal roles of this user mapped from Syncope.
   */
  public function getUserDrupalRolesFromSyncope(UserInterface $user): array {
    $uuid = $user->get('syncope_uuid')->value;
    if (!$uuid) {
      throw new SyncopeUserException('The user does not have a Syncope mapping.');
    }

    // We don't catch this exception because we want a failure.
    $syncope_user = $this->client->getUser($uuid);
    $roles = ['authenticated'];

    $groups = $syncope_user->getGroups();
    if ($groups) {
      $ids = $this->entityTypeManager->getStorage('user_role')->getQuery()
        ->condition('third_party_settings.oe_authorisation_syncope.syncope_group', $groups, 'IN')
        ->execute();

      if ($ids) {
        $roles = array_merge($roles, array_values($ids));
      }
    }

    return $roles;
  }

  /**
   * Given an array of Syncope groups, map them to Drupal roles.
   *
   * @param array $groups
   *   The Syncope group UUIDs.
   *
   * @return array
   *   The roles that can be used in Drupal to assign to a user.
   */
  public function mapSyncopeGroupsToRoles(array $groups): array {
    $ids = $this->entityTypeManager->getStorage('user_role')->getQuery()
      ->condition('third_party_settings.oe_authorisation_syncope.syncope_group', $groups, 'IN')
      ->execute();
    return $ids ? array_values($ids) : [];
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
      $id = $role->getThirdPartySetting('oe_authorisation_syncope', 'syncope_group', NULL);
      if ($id) {
        $group = $this->client->getGroup($id);
        // If found, we do nothing in Syncope but we do set the Group ID.
        $role->setThirdPartySetting('oe_authorisation_syncope', 'syncope_group', $group->getUuid());
        return;
      }
      // If the ID is NULL, it means we can create the Role because it doesn't
      // exist.
      throw new SyncopeGroupNotFoundException('Group not found');
    }
    catch (SyncopeGroupNotFoundException $e) {
      $group = $this->client->createGroup($role->id());
      $role->setThirdPartySetting('oe_authorisation_syncope', 'syncope_group', $group->getUuid());
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
  protected function mapExistingRole(RoleInterface $role) {
    // We can just defer to mapNewRole() as it does the check for existing role.
    $this->mapNewRole($role);
  }

}
