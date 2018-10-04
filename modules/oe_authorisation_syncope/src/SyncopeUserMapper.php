<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\oe_authorisation_syncope\Exception\SyncopeUserNotFoundException;
use Drupal\oe_authorisation_syncope\Syncope\SyncopeUser;
use Drupal\user\UserInterface;

/**
 * Handles the mapping of users with Syncope.
 */
class SyncopeUserMapper {

  /**
   * The Syncope client.
   *
   * @var \Drupal\oe_authorisation_syncope\SyncopeClient
   */
  protected $client;

  /**
   * The role mapper.
   *
   * @var \Drupal\oe_authorisation_syncope\SyncopeRoleMapper
   */
  protected $roleMapper;

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
   * @param \Drupal\oe_authorisation_syncope\SyncopeRoleMapper $roleMapper
   *   The role mapper.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   */
  public function __construct(SyncopeClient $client, SyncopeRoleMapper $roleMapper, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->client = $client;
    $this->roleMapper = $roleMapper;
    $this->logger = $loggerChannelFactory->get('oe_authorisation_syncope');

  }

  /**
   * Acts on the "entity_presave" hook of the User entity.
   *
   * If the user is not correctly kept in sync in Syncope, we throw an exception
   * to prevent Drupal from finishing the process.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   */
  public function preSave(UserInterface $user): void {
    if ($user->isNew()) {
      $this->mapNewUser($user);
      return;
    }

    $this->mapExistingUser($user);
  }

  /**
   * Acts on the "entity_predelete" hook of the User entity.
   *
   * If the user could not be deleted in Syncope, we throw an exception to
   * prevent the delete from happening in Drupal.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function preDelete(UserInterface $user): void {
    $uuid = $user->get('syncope_uuid')->value;
    if (!$uuid) {
      // We do nothing here, not even log.
      return;
    }

    // @todo, see if there is a user to delete.
    $this->client->deleteUser($uuid);
  }

  /**
   * Acts on the "login" hook.
   *
   * Syncs the roles from Syncope with the ones of the user in Drupal. We
   * don't even have to make a call to Syncope because that is taken care of
   * inside SyncopeUserMapper::load().
   *
   * We use this to actually save the roles in Drupal when the user logs in
   * because many of the Drupal access checkers are using the UserSession
   * service which bypasses entity loads and directly queries the database
   * tables to look for the roles.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function login(UserInterface $user): void {
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $storage->resetCache([$user->id()]);
    $user = $storage->load($user->id());
    $user->save();
  }

  /**
   * Acts on the "entity_load" hook.
   *
   * Syncs the roles from Syncope with the ones of the user in Drupal. If
   * Syncope could not be accessed, an exception is thrown and we prevent
   * any roles being added to the user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function load(UserInterface $user): void {
    $uuid = $user->get('syncope_uuid')->value;
    if (!$uuid) {
      // We do nothing here, not even log.
      return;
    }

    $roles = [
      'authenticated',
    ];

    try {
      // Currently the Eu Login ID is the username in Drupal.
      $groups = $this->client->getAllUserGroups($user->label());
    }
    catch (SyncopeUserNotFoundException $e) {
      $this->logger->info('The user that logged in could not be found in Syncope: ' . $user->id());
      // If the user doesn't exist, we remove its roles in case it had any just
      // to prevent them from potentially accessing forbidden things.
      $user->set('roles', $roles);
      return;
    }

    foreach ($groups as $group) {
      $roles[] = $group->getDrupalName();
    }

    $user->set('roles', $roles);
  }

  /**
   * Maps a new user when it first gets created.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  protected function mapNewUser(UserInterface $user): void {
    if ($user->id() == 1) {
      // User 1 does not need to be mapped.
      return;
    }

    try {
      $syncope_user = $this->client->getUser($user->label(), SyncopeClient::USER_IDENTIFIER_USERNAME);
    }
    catch (SyncopeUserNotFoundException $e) {
      $roles = $this->roleMapper->getRolesForUser($user);
      $object = new SyncopeUser('', $user->label(), $roles);
      $syncope_user = $this->client->createUser($object);
    }

    $user->set('syncope_uuid', $syncope_user->getUuid());
  }

  /**
   * Maps an existing user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  protected function mapExistingUser(UserInterface $user): void {
    $uuid = $user->get('syncope_uuid')->value;
    if (!$uuid) {
      $this->logger->info('The user cannot be updated in Syncope because it has no UUID mapped: ' . $user->id());
      return;
    }

    try {
      $this->client->getUser($uuid);
    }
    catch (SyncopeUserNotFoundException $e) {
      $this->logger->info('The user cannot be updated in Syncope because it doesn\'t exist there: ' . $user->id());
      return;
    }

    $roles = $this->roleMapper->getRolesForUser($user);
    $syncope_user = new SyncopeUser($uuid, $user->label(), $roles);
    $this->client->updateUser($syncope_user);
  }

}
