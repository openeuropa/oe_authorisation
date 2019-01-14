<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authorisation\Behat;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\oe_authorisation_syncope\Syncope\SyncopeGroup;
use Drupal\oe_authorisation_syncope\Syncope\SyncopeUser;
use Drupal\oe_authorisation_syncope\SyncopeClient;
use Drupal\oe_authorisation_syncope\SyncopeRoleMapper;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;

/**
 * A context that handles integration with Syncope.
 */
class SyncopeContext extends RawDrupalContext {

  /**
   * The Syncope client.
   *
   * @var \Drupal\oe_authorisation_syncope\SyncopeClient
   */
  protected $syncopeClient;

  /**
   * The Syncope client.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManger;

  /**
   * The role mapper.
   *
   * @var \Drupal\oe_authorisation_syncope\SyncopeRoleMapper
   */
  protected $roleMapper;

  /**
   * The list of Syncope users to clear.
   *
   * @var \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser[]
   */
  protected $syncopeUsers = [];

  /**
   * The config context.
   *
   * @var \Drupal\DrupalExtension\Context\ConfigContext
   */
  protected $configContext;

  /**
   * Gathers some other contexts.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   The before scenario scope.
   *
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope): void {
    $environment = $scope->getEnvironment();
    $this->configContext = $environment->getContext('Drupal\DrupalExtension\Context\ConfigContext');
  }

  /**
   * Returns the Syncope client.
   *
   * @return \Drupal\oe_authorisation_syncope\SyncopeClient
   *   The client.
   */
  protected function getSyncopeClient(): SyncopeClient {
    if (!$this->syncopeClient) {
      $this->syncopeClient = \Drupal::service('oe_authorisation_syncope.client');
    }

    return $this->syncopeClient;
  }

  /**
   * Returns the Entity Type Manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    if (!$this->entityTypeManger) {
      $this->entityTypeManger = \Drupal::entityTypeManager();
    }

    return $this->entityTypeManger;
  }

  /**
   * Returns the role mapper.
   *
   * @return \Drupal\oe_authorisation_syncope\SyncopeRoleMapper
   *   The role mapper.
   */
  protected function getRoleMapper(): SyncopeRoleMapper {
    if (!$this->roleMapper) {
      $this->roleMapper = \Drupal::service('oe_authorisation_syncope.role_mapper');
    }

    return $this->roleMapper;
  }

  /**
   * Creates and authenticates a user with the given global role(s).
   *
   * This is needed because global roles cannot actually be set on a user from
   * Drupal.
   *
   * @Given I am logged in as a user with the global :role role(s)
   * @Given I am logged in as a/an global :role
   */
  public function assertAuthenticatedByRole($role): void {
    // Check if a user with this role is already logged in.
    if (!$this->loggedInWithRole($role)) {
      // Create user (and project)
      $user = (object) [
        'name' => $this->getRandom()->name(8),
        'pass' => $this->getRandom()->name(16),
        'role' => $role,
      ];
      $user->mail = "{$user->name}@example.com";

      $this->userCreate($user);

      $roles = explode(',', $role);
      $roles = array_map('trim', $roles);
      foreach ($roles as $role) {
        if (!in_array(strtolower($role), ['authenticated', 'authenticated user'])) {
          // Only add roles other than 'authenticated user'.
          $this->getDriver()->userAddRole($user, $role);
        }
      }

      // We need to create a global user as well.
      $syncope_user = new SyncopeUser('', $user->name, []);
      $this->syncopeUsers[] = $this->getSyncopeClient()->createUser($syncope_user, 'root');
      // Then add the global roles.
      $this->getSyncopeClient()->addGlobalRoles($user->name, $roles);

      // Login.
      $this->login($user);
    }
  }

  /**
   * Assigns a user role in Syncope.
   *
   * @Given the user :name gets the role :role_name in Syncope
   */
  public function assertUserReceivesRoleInSyncope(string $name, string $role_name): void {
    $user = $this->loadUserByName($name);

    $role = NULL;
    $role_entity = $this->loadRoleByName($role_name);
    $role = $this->getRoleMapper()->getRoleUuid($role_entity);
    $is_global = is_null($role);

    // In case the role is global, we need to create a global user for this user
    // and add the role to that one.
    if ($is_global) {
      // We need to create a global user as well.
      $syncope_user = new SyncopeUser('', $user->label(), []);
      $this->syncopeUsers[] = $this->getSyncopeClient()->createUser($syncope_user, 'root');
      // Then add the global roles.
      $this->getSyncopeClient()->addGlobalRoles($user->label(), [$role_entity->id()]);
      return;
    }

    // Otherwise, we load the user and assign the role.
    $uuid = $user->get('syncope_uuid')->value;
    $syncope_user = $this->getSyncopeClient()->getUser($uuid);
    $roles = $syncope_user->getGroups();
    $roles[] = $role;
    $syncope_user = new SyncopeUser($syncope_user->getUuid(), $user->label(), $roles);
    $this->getSyncopeClient()->updateUser($syncope_user);
  }

  /**
   * Removes a user role in Syncope.
   *
   * @Given the user :name loses the role :role_name in Syncope
   */
  public function assertUserLosesRoleInSyncope(string $name, string $role_name): void {
    $user = $this->loadUserByName($name);

    $role = NULL;
    $role_entity = $this->loadRoleByName($role_name);
    $role = $this->getRoleMapper()->getRoleUuid($role_entity);
    $is_global = is_null($role);

    // In case the role is global, we need to load the user also from the root
    // realm.
    if ($is_global) {
      $syncope_users = $this->getSyncopeClient()->getAllUsers($user->label());
      $root_user = NULL;
      foreach ($syncope_users as $syncope_user) {
        if ($syncope_user->isRootUser()) {
          $root_user = $syncope_user;
          break;
        }
      }

      if (!$root_user) {
        throw new \Exception('The root user is missing. Global role could not be removed.');
      }

      // Remove the global role.
      $roles = [];
      foreach ($root_user->getGroups() as $group_uuid) {
        $group = $this->getSyncopeClient()->getGroup($group_uuid);
        if ($group->getDrupalName() !== $role_entity->id()) {
          $roles[] = $group->getDrupalName();
        }
      }
      $this->getSyncopeClient()->addGlobalRoles($user->label(), $roles);
      return;
    }

    // Otherwise, we load the user and assign the role.
    $uuid = $user->get('syncope_uuid')->value;
    $syncope_user = $this->getSyncopeClient()->getUser($uuid);

    $roles = array_filter($syncope_user->getGroups(), function ($user_role) use ($role) {
      return $user_role !== $role;
    });

    $syncope_user = new SyncopeUser($syncope_user->getUuid(), $user->label(), $roles);
    $this->getSyncopeClient()->updateUser($syncope_user);
  }

  /**
   * Adds the given roles to the user.
   *
   * @Given the user :name has the roles :roles in Drupal
   */
  public function userAddRoles(string $name, string $roles): void {
    $user = $this->loadUserByName($name);
    $role_entities = $this->loadRolesByCommaSeparatedNames($roles);

    /** @var \Drupal\user\RoleInterface $role */
    foreach ($role_entities as $role) {
      if ($role->getThirdPartySetting('oe_authorisation', 'global', FALSE) === FALSE) {
        $user->addRole($role->id());
        continue;
      }

      // For global roles we need to create a global user.
      $syncope_user = new SyncopeUser('', $user->label(), []);
      $this->syncopeUsers[] = $this->getSyncopeClient()->createUser($syncope_user, 'root');
      // Then add the global roles.
      $this->getSyncopeClient()->addGlobalRoles($user->label(), [$role->id()]);
      $user->addRole($role->id());
    }

    $user->save();
  }

  /**
   * Asserts that a user has the given roles in Syncope.
   *
   * @Then the user :name should have the roles :roles in Syncope
   */
  public function assertUserHasRolesInSyncope(string $name, string $roles): void {
    $this->assertUserRolesInSyncope($name, $roles, TRUE);
  }

  /**
   * Asserts that a user does not have the given roles in Syncope.
   *
   * @Then the user :name should not have the roles :roles in Syncope
   */
  public function assertUserHasNotRolesInSyncope(string $name, string $roles): void {
    $this->assertUserRolesInSyncope($name, $roles, FALSE);
  }

  /**
   * Asserts that a role exists in Syncope.
   *
   * @throws \Exception
   *
   * @Then the role :role should exist in Syncope
   */
  public function assertRoleExistsInSyncope(string $role): void {
    $role_entity = $this->loadRoleByName($role);
    $group = $this->getSyncopeClient()->getGroup($this->getRoleMapper()->getRoleUuid($role_entity));
    if (!$group instanceof SyncopeGroup) {
      throw new \Exception('The role could not be found in Syncope.');
    }
  }

  /**
   * Asserts that a user has/does not have the given roles in Syncope.
   *
   * Helper function for the steps.
   *
   * @param string $name
   *   The username.
   * @param string $roles
   *   The user roles.
   * @param bool $has
   *   Whether to check if the user has or does not have those roles.
   *
   * @throws \Exception
   */
  protected function assertUserRolesInSyncope(string $name, string $roles, bool $has): void {
    $user = $this->loadUserByName($name);
    $role_entities = $this->loadRolesByCommaSeparatedNames($roles);
    $user_role_uuids = $this->getSyncopeGroupUuidsFromRoles($role_entities);
    $syncope_user = $this->getSyncopeClient()->getUser($user->get('syncope_uuid')->value);

    // Check if the user has the roles.
    if ($has) {
      if (count(array_intersect($user_role_uuids, $syncope_user->getGroups())) !== count($role_entities)) {
        $existing_roles = [];
        foreach ($syncope_user->getGroups() as $uuid) {
          $existing_roles[] = $role_entities[$this->getSyncopeClient()->getGroup($uuid)->getDrupalName()]->label();
        }
        throw new \Exception(sprintf('The user %s has the Syncope roles %s but should have the roles %s', $name, implode(', ', $existing_roles), $roles));
      }

      return;
    }

    // Otherwise, check if the user is correctly missing the role.
    foreach ($syncope_user->getGroups() as $uuid) {
      if (in_array($uuid, $user_role_uuids)) {
        $group = $this->getSyncopeClient()->getGroup($uuid);
        throw new \Exception(sprintf('The user %s has the Syncope role %s but should not', $name, $group->getDrupalName()));
      }
    }
  }

  /**
   * Cleans up the syncope users.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $afterScenarioScope
   *   The scope.
   *
   * @AfterScenario
   */
  public function cleanUpSyncope(AfterScenarioScope $afterScenarioScope): void {
    foreach ($this->syncopeUsers as $user) {
      $this->getSyncopeClient()->deleteUser($user->getUuid());
    }
  }

  /**
   * Simulates Syncope going down.
   *
   * @Given Syncope goes down
   */
  public function syncopeGoesDown(): void {
    $this->enableTestModuleScanning();
    \Drupal::service('module_installer')->install(['oe_authorisation_syncope_down']);
  }

  /**
   * Simulates Syncope coming back up.
   *
   * Cannot put this in an after scenario hook because other such hooks may fire
   * before it which need Syncope up.
   *
   * @Given Syncope (is) (comes back) up
   */
  public function syncopeComesUp(): void {
    $this->enableTestModuleScanning();
    \Drupal::service('module_installer')->uninstall(['oe_authorisation_syncope_down']);
  }

  /**
   * Loads a user by name.
   *
   * @param string $name
   *   The name.
   *
   * @return \Drupal\user\UserInterface
   *   The entity.
   *
   * @throws \Exception
   */
  protected function loadUserByName(string $name): UserInterface {
    $users = $this->getEntityTypeManager()->getStorage('user')->loadByProperties(['name' => $name]);
    if (!$users) {
      throw new \Exception(sprintf('User %s not found', $name));
    }
    /** @var \Drupal\user\UserInterface $user */
    $user = reset($users);
    return $user;
  }

  /**
   * Loads a role by name.
   *
   * @param string $role
   *   The role name.
   *
   * @return \Drupal\user\RoleInterface
   *   The entity.
   *
   * @throws \Exception
   */
  protected function loadRoleByName(string $role): RoleInterface {
    $roles = $this->getEntityTypeManager()->getStorage('user_role')->loadByProperties(['label' => [$role]]);
    if (!$roles) {
      throw new \Exception(sprintf('The requested role %s could not be found', $role));
    }

    /** @var \Drupal\user\RoleInterface $role */
    $role = reset($roles);
    return $role;
  }

  /**
   * Loads multiple roles by name.
   *
   * @param string $roles
   *   The role names as a string separated by comma.
   *
   * @return \Drupal\user\RoleInterface[]
   *   The entities.
   *
   * @throws \Exception
   */
  protected function loadRolesByCommaSeparatedNames(string $roles): array {
    $roles = explode(', ', $roles);
    $role_entities = $this->getEntityTypeManager()->getStorage('user_role')->loadByProperties(['label' => $roles]);
    if (count($roles) !== count($role_entities)) {
      throw new \Exception('All roles could not be found');
    }

    return $role_entities;
  }

  /**
   * Returns the UUIDs of the Syncope groups that map to these Drupal roles.
   *
   * @param \Drupal\user\RoleInterface[] $roles
   *   The roles.
   *
   * @return array
   *   The uuids.
   */
  protected function getSyncopeGroupUuidsFromRoles(array $roles) {
    $uuids = [];
    foreach ($roles as $entity) {
      $group_id = $this->getRoleMapper()->getRoleUuid($entity);
      if (!$group_id) {
        continue;
      }

      $uuids[] = $group_id;
    }

    return $uuids;
  }

  /**
   * Enables the test module scanning.
   *
   * OE Authorisation Syncope Down is a test module so it cannot be enabled by
   * default as it is not being scanned. By changing the settings temporarily,
   * we can allow that to happen.
   */
  protected function enableTestModuleScanning(): void {
    $settings = Settings::getAll();
    $settings['extension_discovery_scan_tests'] = TRUE;
    // We just have to re-instantiate the singleton.
    new Settings($settings);
  }

}
