<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authorisation_syncope\Kernel;

use Drupal\oe_authorisation_syncope\Syncope\SyncopeUser;

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
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->container->get('entity_type.manager')->getStorage('user')
      ->create(['name' => 'jack']);
    $user->save();

    // Assert we have the user in Syncope.
    $uuid = $user->get('syncope_uuid')->value;
    $this->assertNotEmpty($uuid);
    $syncope_user = $this->getClient()->getUser($uuid);
    $this->assertInstanceOf(SyncopeUser::class, $syncope_user);
    $this->assertEquals('jack@sitea', $syncope_user->getName());
    $this->assertEmpty($syncope_user->getGroups());

    // Make changes to the user.
    $user->setUsername('john');
    $user->addRole('site_manager');
    $user->save();
    $syncope_user = $this->getClient()->getUser($uuid);
    $this->assertInstanceOf(SyncopeUser::class, $syncope_user);
    $this->assertEquals('john@sitea', $syncope_user->getName());
    $groups = $syncope_user->getGroups();
    $this->assertCount(1, $groups);
    $group_uuid = reset($groups);
    $group = $this->getClient()->getGroup($group_uuid);
    $this->assertEquals('site_manager', $group->getDrupalName());

    // Delete the user.
    $user->delete();
  }

  /**
   * Tests that users can be assigned global roles.
   */
  public function testUserWithGlobalRoles(): void {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->container->get('entity_type.manager')->getStorage('user')
      ->create(['name' => 'jack']);
    $user->save();

    // Test the client can assign the global role.
    $root_user = new SyncopeUser('', $user->label(), []);
    $this->getClient()->createUser($root_user, 'root');
    $this->getClient()->addGlobalRoles($user->getDisplayName(), ['support_engineer']);

    $syncope_users = $this->getClient()->getAllUsers($user->label());
    $this->assertCount(2, $syncope_users);
    foreach ($syncope_users as $syncope_user) {
      $groups = $syncope_user->getGroups();
      if ($syncope_user->isRootUser()) {
        $this->assertEquals('jack', $syncope_user->getName());
        $this->assertCount(1, $groups);
        $uuid = reset($groups);
        $group = $this->getClient()->getGroup($uuid);
        $this->assertEquals('support_engineer', $group->getDrupalName());

        $this->getClient()->deleteUser($syncope_user->getUuid());
        continue;
      }

      $this->assertEquals('jack@sitea', $syncope_user->getName());
      $this->assertEmpty($groups);
      $this->getClient()->deleteUser($syncope_user->getUuid());
    }
  }

}
