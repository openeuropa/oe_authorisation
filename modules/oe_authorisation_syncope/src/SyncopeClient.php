<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
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
 *
 * @SuppressWarnings(PHPMD)
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
   * The identifier to use for retrieving a group by UUID.
   */
  const GROUP_IDENTIFIER_UUID = 'uuid';

  /**
   * The identifier to use for retrieving a group by name.
   */
  const GROUP_IDENTIFIER_NAME = 'name';


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
   * The State API.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The Drupal config object for Syncope.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Stores the UUIDs of the Syncope realms.
   *
   * @var array
   */
  protected $syncopeRealmUuids = [];

  /**
   * SyncopeClient constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State API.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $client, LoggerChannelFactoryInterface $loggerChannelFactory, StateInterface $state) {
    $this->client = $client;
    $this->config = $configFactory->get('oe_authorisation_syncope.settings');
    $this->configuration = Configuration::getDefaultConfiguration()
      ->setUsername($this->config->get('credentials.username'))
      ->setPassword($this->config->get('credentials.password'))
      ->setHost($this->config->get('endpoint'));
    $this->syncopeDomain = $this->config->get('domain');
    $this->siteRealm = $this->config->get('site_realm_name');
    $this->logger = $loggerChannelFactory->get('oe_authorisation_syncope');
    $this->state = $state;
  }

  /**
   * Returns the UUID of a given realm.
   *
   * @param string $realm
   *   The realm to get the UUID for.
   *
   * @return string|null
   *   The UUID.
   */
  protected function getRealmUuid(string $realm):? string {
    $this->ensureRealmUuids();
    return isset($this->syncopeRealmUuids[$realm]) ? $this->syncopeRealmUuids[$realm] : NULL;
  }

  /**
   * Ensures that the UUIDs of the Syncope realms are stored.
   */
  protected function ensureRealmUuids(): void {
    if (!empty($this->syncopeRealmUuids)) {
      return;
    }

    $site_realm_uuid = $this->state->get('oe_authorisation_syncope.site_realm_uuid');
    $root_realm_uuid = $this->state->get('oe_authorisation_syncope.root_realm_uuid');
    if ($site_realm_uuid && $root_realm_uuid) {
      $this->syncopeRealmUuids['site'] = $site_realm_uuid;
      $this->syncopeRealmUuids['root'] = $root_realm_uuid;
      return;
    }

    $realm = $this->getSiteRealm();
    $site_realm_uuid = $realm->getUuid();
    $root_realm_uuid = $realm->getParent();
    $this->syncopeRealmUuids['site'] = $site_realm_uuid;
    $this->syncopeRealmUuids['root'] = $root_realm_uuid;
    $this->state->set('oe_authorisation_syncope.site_realm_uuid', $site_realm_uuid);
    $this->state->set('oe_authorisation_syncope.root_realm_uuid', $root_realm_uuid);
  }

  /**
   * Returns the Syncope realm of the site.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeRealm
   *   The Syncope realm object.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeException
   */
  protected function getSiteRealm(): SyncopeRealm {
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
   * @param string $identifier_type
   *   Whether to identify by UUID or name.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeGroup
   *   The Syncope group.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeGroupException
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeGroupNotFoundException
   */
  public function getGroup(string $identifier, $identifier_type = self::GROUP_IDENTIFIER_UUID): SyncopeGroup {
    $api = new GroupsApi($this->client, $this->configuration);
    if ($identifier_type === self::GROUP_IDENTIFIER_NAME) {
      $identifier .= '@' . $this->siteRealm;
    }

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
   * @param string $destination_realm
   *   The destination realm: site or root.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser
   *   The Syncope user.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeUserException
   */
  public function createUser(SyncopeUser $user, $destination_realm = 'site'): SyncopeUser {
    $api = new AnyObjectsApi($this->client, $this->configuration);

    $memberships = [];
    foreach ($user->getGroups() as $role) {
      $memberships[] = [
        'groupKey' => $role,
      ];
    }

    if ($destination_realm === 'site') {
      $username = $user->getName() . '@' . $this->siteRealm;
      $realm = '/' . $this->siteRealm;
    }
    else {
      $username = $user->getName();
      $realm = '/';
    }

    $payload = new \stdClass();
    $payload->{'@class'} = 'org.apache.syncope.common.lib.to.AnyObjectTO';
    $payload->realm = $realm;
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
   * @param bool $update_role
   *   Whether to update the roles as well. By default we don't update roles.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser
   *   The Syncope user.
   *
   * @throws \Drupal\oe_authorisation_syncope\Exception\SyncopeUserException
   */
  public function updateUser(SyncopeUser $user, $update_role = FALSE): SyncopeUser {
    if ($user->getUuid() == "") {
      $this->logger->error(sprintf('There user %s could not be updated because the UUID is missing.', $user->getName()));
      throw new SyncopeUserException('Missing UUID for updating the user.');
    }

    $api = new AnyObjectsApi($this->client, $this->configuration);

    $username = $user->getName() . '@' . $this->siteRealm;
    $payload = new \stdClass();
    $payload->key = $user->getUuid();
    $payload->{'@class'} = 'org.apache.syncope.common.lib.patch.AnyObjectPatch';
    $payload->name = [
      'operation' => 'ADD_REPLACE',
      'value' => $username,
    ];

    if ($update_role) {
      $memberships = $this->prepareMembershipsPatch($user);
      if ($memberships) {
        $payload->memberships = $memberships;
      }
    }

    // @todo move this out of here and set the EULogin ID dynamically.
    $payload->plainAttrs = [
      [
        'operation' => 'ADD_REPLACE',
        'attrTO' => [
          'schema' => 'eulogin_id',
          'values' => [$user->getName()],
        ]
      ]
    ];

    try {
      $response = $api->updateAnyObjectPartial($user->getUuid(), $this->syncopeDomain, $payload);
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
    if ($identifier_type === self::USER_IDENTIFIER_USERNAME) {
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
   * Gets all the users from Syncope that match one EU Login ID.
   *
   * Since a user is represented as an OeUser at each level of the realm
   * hierarchy, we need to perform a query by the unique identifier of that
   * user at all levels.
   *
   * The unique ID in this case is the EULogin ID.
   *
   * @param string $eu_login
   *   The EU Login ID of the user.
   * @param array $realms
   *   Which realms to query in.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser[]
   *   The list of users.
   */
  public function getAllUsers(string $eu_login, array $realms = []): array {
    if (empty($realms)) {
      $realms = [
        $this->getRealmUuid('root'),
        $this->getRealmUuid('site'),
      ];
    }
    $realms_fiql = '(';
    foreach ($realms as $key => $realm) {
      $realms_fiql .= 'realm==' . $realm;
      if ($key === (count($realms) - 1)) {
        $realms_fiql .= ')';
        break;
      }
      $realms_fiql .= ',';
    }
    $fiql = new FormattableMarkup('$type==OeUser;eulogin_id==@eulogin_id;@realms', ['@eulogin_id' => $eu_login, '@realms' => $realms_fiql]);
    $api = new AnyObjectsApi($this->client, $this->configuration);
    try {
      $response = $api->searchAnyObject($this->syncopeDomain, 1, 5, NULL, NULL, NULL, (string) $fiql);
    }
    catch (\Exception $e) {
      throw new SyncopeUserException(sprintf('There was a problem querying the user %s: %s', $eu_login, $e->getMessage()));
    }

    if (empty($response->result)) {
      throw new SyncopeUserNotFoundException(sprintf('The user could not be found by the EU Login ID %s in these realms: %s.', $eu_login, implode(', ', $realms)));
    }

    $users = [];
    foreach ($response->result as $object) {
      $memberships = $object->memberships;
      $groups = [];
      foreach ($memberships as $membership) {
        $groups[] = $membership->groupKey;
      }

      $users[] = new SyncopeUser($object->key, $object->name, $groups);
    }

    return $users;
  }

  /**
   * Gets all the user groups from Syncope.
   *
   * Since a user is represented as an OeUser at each level of the realm
   * hierarchy, we need to perform a query by the unique identifier of that
   * user to get the roles at all levels.
   *
   * The unique ID in this case is the EULogin ID.
   *
   * @param string $eu_login
   *   The EU Login ID of the user.
   * @param array $realms
   *   Which realms to query in.
   *
   * @return \Drupal\oe_authorisation_syncope\Syncope\SyncopeGroup[]
   *   The Syncope group object.
   */
  public function getAllUserGroups(string $eu_login, array $realms = []): array {
    $users = $this->getAllUsers($eu_login, $realms);

    $groups = [];

    foreach ($users as $user) {
      foreach ($user->getGroups() as $uuid) {
        $groups[] = $this->getGroup($uuid);
      }
    }

    return $groups;
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
    $api = new AnyObjectsApi($this->client, $this->configuration);
    try {
      $api->deleteAnyObject($identifier, $this->syncopeDomain);
    }
    catch (\Exception $e) {
      $this->logger->error(sprintf('There was a problem deleting the user %s: %s.', $identifier, $e->getMessage()));
      throw new SyncopeUserException('There was a problem deleting the user.');
    }
  }

  /**
   * Adds global roles to a root user.
   *
   * @param string $eu_login
   *   The unique EU Login ID.
   * @param array $roles
   *   The roles.
   */
  public function addGlobalRoles(string $eu_login, array $roles = []): void {
    $users = $this->getAllUsers($eu_login);
    $root_user = NULL;
    foreach ($users as $user) {
      if ($user->isRootUser()) {
        $root_user = $user;
      }
    }

    if (!$root_user) {
      throw new SyncopeUserException('The root user is missing. We cannot add a global role.');
    }

    // @todo check if the role is set already.
    $api = new AnyObjectsApi($this->client, $this->configuration);

    $memberships = [];
    foreach ($roles as $role) {
      $group = $this->getGroup($role);
      $memberships[] = [
        'groupKey' => $group->getUuid(),
      ];
    }

    $payload = new \stdClass();
    $payload->{'@class'} = 'org.apache.syncope.common.lib.to.AnyObjectTO';
    $payload->realm = '/';
    $payload->memberships = $memberships;
    $payload->name = $root_user->getName();
    $payload->type = 'OeUser';
    $payload->key = $root_user->getUuid();
    // @todo move this out of here and set the EULogin ID dynamically.
    $payload->plainAttrs = [
      [
        'schema' => 'eulogin_id',
        'values' => [$root_user->getName()],
      ],
    ];

    try {
      $response = $api->updateAnyObject($root_user->getUuid(), $this->syncopeDomain, $payload);
    }
    catch (\Exception $e) {
      $this->logger->error(sprintf('There was a problem updating the user %s: %s.', $user->getName(), $e->getMessage()));
      throw new SyncopeUserException('There was a problem updating the user.');
    }

    if (!isset($response->entity)) {
      $this->logger->error(sprintf('There was a problem creating the user %s.', $user->getName()));
      throw new SyncopeUserException('There was a problem updating the user.');
    }
  }

  /**
   * Prepares the membership patch data for a user role update.
   *
   * @param \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser $user
   *
   * @return array
   */
  protected function prepareMembershipsPatch(SyncopeUser $user): array {
    $memberships = [];
    $existing_user = $this->getUser($user->getUuid());
    $existing_memberships = $existing_user->getGroups();

    $to_delete = array_diff($existing_memberships, $user->getGroups());
    foreach ($to_delete as $uuid) {
      $memberships[] = [
        'operation' => 'DELETE',
        'group' => $uuid
      ];
    }

    foreach ($user->getGroups() as $group) {
      $memberships[] = [
        'operation' => 'ADD_REPLACE',
        'group' => $group
      ];
    }

    return $memberships;
  }

}
