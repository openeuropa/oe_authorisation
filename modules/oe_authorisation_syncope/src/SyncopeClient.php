<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\oe_authorisation_syncope\Exception\SyncopeException;
use Drupal\oe_authorisation_syncope\Exception\SyncopeGroupException;
use Drupal\oe_authorisation_syncope\Exception\SyncopeGroupNotFoundException;
use Drupal\oe_authorisation_syncope\Exception\SyncopeUserException;
use Drupal\oe_authorisation_syncope\Exception\SyncopeUserNotFoundException;
use Drupal\oe_authorisation_syncope\Syncope\SyncopeRealm;
use Drupal\oe_authorisation_syncope\Syncope\SyncopeGroup;
use Drupal\oe_authorisation_syncope\Syncope\SyncopeUser;
use GuzzleHttp\ClientInterface;
use OpenEuropa\SyncopePhpClient\Api\AnyObjectsApi;
use OpenEuropa\SyncopePhpClient\Api\GroupsApi;
use OpenEuropa\SyncopePhpClient\Api\RealmsApi;
use OpenEuropa\SyncopePhpClient\Configuration;

/**
 * Service that wraps the Syncope PHP client.
 */
class SyncopeClient {

  /**
   * The identifier to use for retrieving a user by UUID.
   */
  const USER_IDENTIFIER_UUID = 'uuid';

  /**
   * The identifier to use for retrieving a user by username.
   */
  const USER_IDENTIFIER_USERNAME = 'username';

  /**
   * The configuration data.
   *
   * @var \OpenEuropa\SyncopePhpClient\Configuration
   */
  protected $configuration;

  /**
   * The Syncope domain.
   *
   * @var string
   */
  protected $syncopeDomain;

  /**
   * The name of the Syncope realm that maps to this site.
   *
   * @var string
   */
  protected $siteRealm;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * SyncopeClient constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $client, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->client = $client;
    $config = $configFactory->get('oe_authorisation_syncope.settings');
    $this->configuration = Configuration::getDefaultConfiguration()
      ->setUsername($config->get('credentials.username'))
      ->setPassword($config->get('credentials.password'))
      ->setHost($config->get('endpoint'));
    $this->syncopeDomain = $config->get('domain');
    $this->siteRealm = $config->get('site_realm_name');
    $this->logger = $loggerChannelFactory->get('oe_authorisation_syncope');
  }

  /**
   * Returns the Syncope realm of the site.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeRealm
   *   The Syncope realm object.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeException
   */
  public function getSiteRealm() {
    $api = new RealmsApi($this->client, $this->configuration);

    try {
      $response = $api->listRealm($this->siteRealm, $this->syncopeDomain);
      $object = reset($response);
      return new SyncopeRealm($object->key, $object->name, $object->fullPath, $object->parent);
    }
    catch (\Exception $e) {
      $this->logger->error('The site realm could not be retrieved from Syncope. An error has occurred: ' . $e->getMessage());
      throw new SyncopeException('There was a problem getting the site realm from Syncope: ' . $e->getMessage());
    }
  }

  /**
   * Creates a new group in Syncope.
   *
   * @param string $name
   *   The machine name of the group.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeGroup
   *   The Syncope group object.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeGroupException
   */
  public function createGroup(string $name): SyncopeGroup {
    $api = new GroupsApi($this->client, $this->configuration);
    $realm = $this->siteRealm;

    $group_name = "$name@$realm";

    $payload = new \stdClass();
    $payload->{'@class'} = 'org.apache.syncope.common.lib.to.GroupTO';
    $payload->realm = '/' . $this->siteRealm;
    $payload->name = $group_name;

    try {
      $response = $api->createGroup($this->syncopeDomain, $payload);
    }
    catch (\Exception $e) {
      $this->logger->error(sprintf('There was a problem creating the group %s: %s.', $group_name, $e->getMessage()));
      throw new SyncopeGroupException('There was a problem creating the group.');
    }

    if (!isset($response->entity)) {
      $this->logger->error(sprintf('There was a problem creating the group %s.', $group_name));
      throw new SyncopeGroupException('There was a problem creating the group.');
    }

    $object = $response->entity;
    $drupal_name = str_replace('@' . $this->siteRealm, '', $object->name);
    return new SyncopeGroup($object->key, $object->name, $drupal_name);
  }

  /**
   * Retrieves a group from Syncope.
   *
   * @param string $identifier
   *   Can be either the UUID or the name.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeGroup
   *   The Syncope group.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeGroupException
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeGroupNotFoundException
   */
  public function getGroup(string $identifier) {
    $api = new GroupsApi($this->client, $this->configuration);

    try {
      $response = $api->readGroup($identifier, $this->syncopeDomain);
    }
    catch (\Exception $e) {
      if ($e->getCode() === 404) {
        throw new SyncopeGroupNotFoundException('The group was not found.');
      }
      $this->logger->error(sprintf('There was a problem retrieving the group %s: %s.', $identifier, $e->getMessage()));
      throw new SyncopeGroupException('There was a problem retrieving the group.');
    }

    $drupal_name = str_replace('@' . $this->siteRealm, '', $response->name);
    return new SyncopeGroup($response->key, $response->name, $drupal_name);
  }

  /**
   * Deletes a group in Syncope. Throws exception if the operation failed.
   *
   * @param string $uuid
   *   The UUID of the group.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeGroupException
   */
  public function deleteGroup(string $uuid): void {
    try {
      $this->getGroup($uuid);
    }
    catch (SyncopeGroupNotFoundException $e) {
      $this->logger->error('The Syncope group could not be deleted because it does not exist: ' . $uuid);
      return;
    }

    $api = new GroupsApi($this->client, $this->configuration);

    try {
      $api->deleteGroup($uuid, $this->syncopeDomain);
    }
    catch (\Exception $e) {
      $this->logger->error(sprintf('There was a problem deleting the group %s: %s.', $uuid, $e->getMessage()));
      throw new SyncopeGroupException('There was a problem deleting the group.');
    }
  }

  /**
   * Creates a user in Syncope.
   *
   * @param \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser $user
   *   The Syncope user.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser
   *   The Syncope user.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeUserException
   */
  public function createUser(SyncopeUser $user): SyncopeUser {
    $api = new AnyObjectsApi($this->client, $this->configuration);

    $memberships = [];
    foreach ($user->getGroups() as $role) {
      $memberships[] = [
        'groupKey' => $role,
      ];
    }

    $username = $user->getName() . '@' . $this->siteRealm;
    $payload = new \stdClass();
    $payload->{'@class'} = 'org.apache.syncope.common.lib.to.AnyObjectTO';
    $payload->realm = '/' . $this->siteRealm;
    $payload->memberships = $memberships;
    $payload->name = $username;
    $payload->type = 'OeUser';
    // @todo move this out of here and set the EULogin ID dynamically.
    $payload->plainAttrs = [
      [
        'schema' => 'eulogin_id',
        'values' => [$user->getName()],
      ],
    ];

    try {
      $response = $api->createAnyObject($this->syncopeDomain, $payload);
    }
    catch (\Exception $e) {
      $this->logger->error(sprintf('There was a problem creating the user %s: %s.', $user->getName(), $e->getMessage()));
      throw new SyncopeUserException('There was a problem creating the user.');
    }

    if (!isset($response->entity)) {
      $this->logger->error(sprintf('There was a problem creating the user %s.', $user->getName()));
      throw new SyncopeUserException('There was a problem creating the user.');
    }

    $object = $response->entity;
    $memberships = $object->memberships;
    $groups = [];
    foreach ($memberships as $membership) {
      $groups[] = $membership->groupKey;
    }

    return new SyncopeUser($object->key, $object->name, $groups);
  }

  /**
   * Updates a user in Syncope.
   *
   * @param \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser $user
   *   The Syncope user.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser
   *   The Syncope user.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeUserException
   */
  public function updateUser(SyncopeUser $user): SyncopeUser {
    if ($user->getUuid() == "") {
      $this->logger->error(sprintf('There user %s could not be updated because the UUID is missing.', $user->getName()));
      throw new SyncopeUserException('Missing UUID for updating the user.');
    }

    $api = new AnyObjectsApi($this->client, $this->configuration);

    $memberships = [];
    foreach ($user->getGroups() as $role) {
      $memberships[] = [
        'groupKey' => $role,
      ];
    }

    $username = $user->getName() . '@' . $this->siteRealm;
    $payload = new \stdClass();
    $payload->{'@class'} = 'org.apache.syncope.common.lib.to.AnyObjectTO';
    $payload->realm = '/' . $this->siteRealm;
    $payload->memberships = $memberships;
    $payload->name = $username;
    $payload->type = 'OeUser';
    $payload->key = $user->getUuid();
    // @todo move this out of here and set the EULogin ID dynamically.
    $payload->plainAttrs = [
      [
        'schema' => 'eulogin_id',
        'values' => [$user->getName()],
      ],
    ];

    try {
      $response = $api->updateAnyObject($user->getUuid(), $this->syncopeDomain, $payload);
    }
    catch (\Exception $e) {
      $this->logger->error(sprintf('There was a problem updating the user %s: %s.', $user->getName(), $e->getMessage()));
      throw new SyncopeUserException('There was a problem updating the user.');
    }

    if (!isset($response->entity)) {
      $this->logger->error(sprintf('There was a problem creating the user %s.', $user->getName()));
      throw new SyncopeUserException('There was a problem updating the user.');
    }

    $object = $response->entity;
    $memberships = $object->memberships;
    $groups = [];
    foreach ($memberships as $membership) {
      $groups[] = $membership->groupKey;
    }

    return new SyncopeUser($object->key, $object->name, $groups);
  }

  /**
   * Retrieves a user from Syncope.
   *
   * @param string $identifier
   *   Can be either the User UUID of the username.
   * @param string $identifier_type
   *   The type of identifier: 'uuid' or 'username'.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser
   *   The Syncope user.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeUserException
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeUserNotFoundException
   */
  public function getUser(string $identifier, $identifier_type = self::USER_IDENTIFIER_UUID): SyncopeUser {
    $api = new AnyObjectsApi($this->client, $this->configuration);
    if ($identifier_type === 'username') {
      $identifier .= '@' . $this->siteRealm;
    }

    try {
      $response = $api->readAnyObject($identifier, $this->syncopeDomain);
    }
    catch (\Exception $e) {
      if ($e->getCode() === 404) {
        throw new SyncopeUserNotFoundException('The user was not found.');
      }
      throw new SyncopeUserException(sprintf('There was a problem retrieving the user %s: %s', $identifier, $e->getMessage()));
    }

    if ($response->type !== 'OeUser') {
      throw new SyncopeUserNotFoundException('The user was not found.');
    }

    if ($response->realm !== '/' . $this->siteRealm) {
      throw new SyncopeUserNotFoundException('The user was found but is in the wrong realm.');
    }

    $memberships = $response->memberships;
    $groups = [];
    foreach ($memberships as $membership) {
      $groups[] = $membership->groupKey;
    }

    return new SyncopeUser($response->key, $response->name, $groups);
  }

  /**
   * Deletes a user from Syncope.
   *
   * @param string $identifier
   *   Can be either the User UUID of the username.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeUserException
   */
  public function deleteUser(string $identifier): void {
    try {
      $this->getUser($identifier);
    }
    catch (SyncopeUserNotFoundException $e) {
      $this->logger->info('A user was attempted to be removed but was not found in Syncope: ' . $identifier);
      // If the user does not exist, we cannot delete it. But we don't need to
      // fail.
      return;
    }

    $api = new AnyObjectsApi($this->client, $this->configuration);
    try {
      $api->deleteAnyObject($identifier, $this->syncopeDomain);
    }
    catch (\Exception $e) {
      $this->logger->error(sprintf('There was a problem deleting the user %s: %s.', $identifier, $e->getMessage()));
      throw new SyncopeUserException('There was a problem deleting the user.');
    }
  }

}
