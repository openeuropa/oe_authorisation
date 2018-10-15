<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authorisation_syncope\Kernel;

use Drupal\oe_authorisation_syncope\Exception\SyncopeUserNotFoundException;
use Drupal\oe_authorisation_syncope\Syncope\SyncopeUser;
use Drupal\user\UserInterface;

/**
 * Tests the Syncope user mapping.
 */
class SyncopeUserTest extends SyncopeTestBase {

  /**
   * Checks the CRUD of users in Drupal.
   *
   * Ensures the corresponding actions take place in Syncope.
   */
  public function testUserLifeCycle(): void {
    $user = $this->createUser('Kevin');

    // Assert we have the user in Syncope.
    $uuid = $user->get('syncope_uuid')->value;
    $this->assertNotEmpty($uuid);
    $syncope_user = $this->getClient()->getUser($uuid);
    $this->assertInstanceOf(SyncopeUser::class, $syncope_user);
    $this->assertEquals('Kevin@sitea', $syncope_user->getName());
    $this->assertEmpty($syncope_user->getGroups());

    // Make changes to the user.
    $user->setUsername('Mark');
    $user->addRole('site_manager');
    $user->save();

    // Assert the changes have taken effect.
    $syncope_user = $this->getClient()->getUser($uuid);
    $this->assertInstanceOf(SyncopeUser::class, $syncope_user);
    $this->assertEquals('Mark@sitea', $syncope_user->getName());
    $groups = $syncope_user->getGroups();
    $this->assertCount(1, $groups);
    $group_uuid = reset($groups);
    $group = $this->getClient()->getGroup($group_uuid);
    $this->assertEquals('site_manager', $group->getDrupalName());

    // Delete the user.
    $user->delete();
    // Assert the user has been deleted.
    try {
      $syncope_user = $this->getClient()->getUser($uuid);
    }
    catch (SyncopeUserNotFoundException $exception) {
      $this->assertEquals("The user was not found.", $exception->getMessage());
    }
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
      $this->getClient()->deleteUser($syncope_user->getUuid());
    }
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
    return $user;
  }

}
