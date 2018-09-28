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
   * Acts on the "user_login" hook.
   *
   * Syncs the roles from Syncope with the ones of the user in Drupal. If
   * Syncope could not be accessed, an exception is thrown the prevent the user
   * from logging in.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function login(UserInterface $user): void {
    $uuid = $user->get('syncope_uuid')->value;
    if (!$uuid) {
      // We do nothing here, not even log.
      return;
    }

    try {
      // Currently the Eu Login ID is the username in Drupal.
      $groups = $this->client->getAllUserGroups($user->label());
    }
    catch (SyncopeUserNotFoundException $e) {
      $this->logger->info('The user that logged in could not be found in Syncope: ' . $user->id());
      // In this we don't try to map anything with Syncope cause the user does
      // not exist.
      return;
    }

    $roles = [
      'authenticated',
    ];

    foreach ($groups as $group) {
      $roles[] = $group->getDrupalName();
    }

    $user->set('roles', $roles);
    $user->save();
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
      // We don't send any roles to Syncope.
      $object = new SyncopeUser('', $user->label(), []);
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

    // We don't update the roles in Syncope.
    $syncope_user = new SyncopeUser($uuid, $user->label(), []);
    $this->client->updateUser($syncope_user);
  }

}
