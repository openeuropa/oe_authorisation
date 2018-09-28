<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authorisation_syncope\Kernel;

use Drupal\oe_authorisation_syncope\Syncope\SyncopeUser;
use Drupal\user\UserInterface;

/**
 * Tests the Syncope user mapping.
 */
class SyncopeUserTest extends SyncopeTestBase {

  /**
   * Users to delete if the tests fail.
   *
   * @var array
   */
  protected $users = [];

  /**
   * Checks the CRUD of users in Drupal.
   *
   * Ensures the corresponding actions take place in Syncope.
   */
  public function testUserLifeCycle(): void {
    $user = $this->createUser('Kevin');
    $uuid = $user->get('syncope_uuid')->value;

    // Assert we have the user in Syncope.
    $this->assertNotEmpty($uuid);
    $syncope_user = $this->getClient()->getUser($uuid);
    $this->assertInstanceOf(SyncopeUser::class, $syncope_user);
    $this->assertEquals('Kevin@sitea', $syncope_user->getName());
    $this->assertEmpty($syncope_user->getGroups());

    // Add a role in Syncope.
    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load('editor');
    $role_uuid = $this->container->get('oe_authorisation_syncope.role_mapper')->getRoleUuid($role);
    $syncope_user = new SyncopeUser($uuid, $user->label(), [$role_uuid]);
    $this->getClient()->updateUser($syncope_user, TRUE);

    // Make changes to the user in Drupal.
    $user->setUsername('Mark');
    $user->addRole('site_manager');
    $user->save();

    // Assert that only the user name got changed in Syncope.
    $syncope_user = $this->getClient()->getUser($uuid);
    $this->assertInstanceOf(SyncopeUser::class, $syncope_user);
    $this->assertEquals('Mark@sitea', $syncope_user->getName());
    $groups = $syncope_user->getGroups();
    $this->assertCount(1, $groups);
    $group_uuid = reset($groups);
    $group = $this->getClient()->getGroup($group_uuid);
    $this->assertEquals('editor', $group->getDrupalName());

    // Delete the user.
    $user->delete();
    // @todo assert the user got deleted.
  }

  /**
   * Tests that a user can already pre-exist in Syncope and the mapping works.
   */
  public function testUserAlreadyExistsInSyncope(): void {
    // Pre-create a user in Syncope with the Site Manager role.
    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load('site_manager');
    $uuid = $this->container->get('oe_authorisation_syncope.role_mapper')->getRoleUuid($role);

    $syncope_user = new SyncopeUser('', 'Kevin', [$uuid]);
    $syncope_user = $this->getClient()->createUser($syncope_user);
    $this->assertCount(1, $syncope_user->getGroups());

    // Create a user in Drupal with the same name.
    $user = $this->createUser('Kevin');

    // Assert the user is mapped and still has its role.
    $syncope_user_bis = $this->getClient()->getUser($user->get('syncope_uuid')->value);
    $this->assertInstanceOf(SyncopeUser::class, $syncope_user_bis);
    $this->assertEquals($syncope_user_bis->getUuid(), $syncope_user->getUuid());
    $this->assertCount(1, $syncope_user_bis->getGroups());

    // Clear the user.
    $user->delete();
  }

  /**
   * Tests that users can be assigned global roles.
   */
  public function testUserWithGlobalRoles(): void {
    $user = $this->createUser('Kevin');

    // Test the client can assign the global role.
    $root_user = new SyncopeUser('', $user->label(), []);
    $this->getClient()->createUser($root_user, 'root');
    $this->getClient()->addGlobalRoles($user->getDisplayName(), ['support_engineer']);

    $syncope_users = $this->getClient()->getAllUsers($user->label());
    $this->assertCount(2, $syncope_users);
    foreach ($syncope_users as $syncope_user) {
      $groups = $syncope_user->getGroups();
      if ($syncope_user->isRootUser()) {
        $this->assertEquals('Kevin', $syncope_user->getName());
        $this->assertCount(1, $groups);
        $uuid = reset($groups);
        $group = $this->getClient()->getGroup($uuid);
        $this->assertEquals('support_engineer', $group->getDrupalName());

        $this->getClient()->deleteUser($syncope_user->getUuid());
        continue;
      }

      $this->assertEquals('Kevin@sitea', $syncope_user->getName());
      $this->assertEmpty($groups);
    }
  }

  /**
   * Tests that roles can be added/removed to a given Syncope user via the API.
   */
  public function testRoleUpdate(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('user_role');
    /** @var \Drupal\oe_authorisation_syncope\SyncopeRoleMapper $mapper */
    $mapper = $this->container->get('oe_authorisation_syncope.role_mapper');
    $editor = $storage->load('editor');
    $site_manager = $storage->load('site_manager');
    $editor_uuid = $mapper->getRoleUuid($editor);
    $site_manager_uuid = $mapper->getRoleUuid($site_manager);

    $syncope_user = new SyncopeUser('', 'Kevin', []);
    $syncope_user = $this->getClient()->createUser($syncope_user);

    // Update without role.
    $syncope_user = new SyncopeUser($syncope_user->getUuid(), 'Kevin', [$editor_uuid, $site_manager_uuid]);
    $this->getClient()->updateUser($syncope_user);
    $syncope_user = $this->getClient()->getUser($syncope_user->getUuid());
    $this->assertEmpty($syncope_user->getGroups());

    // Add the editor role.
    $syncope_user = new SyncopeUser($syncope_user->getUuid(), 'Kevin', [$editor_uuid]);
    $this->getClient()->updateUser($syncope_user, TRUE);
    $syncope_user = $this->getClient()->getUser($syncope_user->getUuid());
    $this->assertCount(1, $syncope_user->getGroups());

    // Add the site manager role as well.
    $syncope_user = new SyncopeUser($syncope_user->getUuid(), 'Kevin', [$editor_uuid, $site_manager_uuid]);
    $this->getClient()->updateUser($syncope_user, TRUE);
    $syncope_user = $this->getClient()->getUser($syncope_user->getUuid());
    $this->assertCount(2, $syncope_user->getGroups());

    // Remove the editor role but keep site manager.
    $syncope_user = new SyncopeUser($syncope_user->getUuid(), 'Kevin', [$site_manager_uuid]);
    $this->getClient()->updateUser($syncope_user, TRUE);
    $syncope_user = $this->getClient()->getUser($syncope_user->getUuid());
    $this->assertCount(1, $syncope_user->getGroups());
    $groups = $syncope_user->getGroups();
    $group_uuid = reset($groups);
    $group = $this->getClient()->getGroup($group_uuid);
    $this->assertEquals('site_manager', $group->getDrupalName());

    // Delete the user.
    $this->getClient()->deleteUser($syncope_user->getUuid());
  }

  /**
   * Creates a user.
   *
   * @param string $name
   *   The name.
   *
   * @return \Drupal\user\UserInterface
   *   The user.
   */
  protected function createUser(string $name): UserInterface {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->container->get('entity_type.manager')->getStorage('user')
      ->create(['name' => $name]);

    $user->save();
    $this->users[] = $user->id();
    return $user;
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // If the tests fail and the users don't get deleted, they linger in
    // Syncope. So we delete them here if they have not be deleted.
    if (!$this->users) {
      return;
    }

    $users = $this->container->get('entity_type.manager')->getStorage('user')->loadMultiple($this->users);
    if ($users) {
      $this->container->get('entity_type.manager')->getStorage('user')->delete($users);
    }

    parent::tearDown();
  }

}
