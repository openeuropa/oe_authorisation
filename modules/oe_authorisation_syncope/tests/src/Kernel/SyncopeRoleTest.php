<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authorisation_syncope\Kernel;

use Drupal\oe_authorisation_syncope\Exception\SyncopeGroupNotFoundException;
use Drupal\oe_authorisation_syncope\Syncope\SyncopeGroup;
use Drupal\oe_authorisation_syncope\SyncopeClient;

/**
 * Tests the Syncope role mapping.
 */
class SyncopeRoleTest extends SyncopeTestBase {

  /**
   * Checks the CRUD of roles in Drupal.
   *
   * Ensures the corresponding actions take place in Syncope.
   */
  public function testRoleLifeCycle(): void {
    $this->container->get('entity_type.manager')->getStorage('user_role')
      ->create(['id' => 'my_test_role', 'label' => 'My Test Role'])
      ->save();

    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load('my_test_role');

    // Check in Syncope that we have the role by UUID.
    $uuid = \Drupal::service('oe_authorisation_syncope.role_mapper')->getRoleUuid($role);
    $group = $this->getClient()->getGroup($uuid);
    $this->assertInstanceOf(SyncopeGroup::class, $group);

    // Check in Syncope we have the role by machine name.
    $group = $this->getClient()->getGroup($role->id(), SyncopeClient::IDENTIFIER_NAME);
    $this->assertInstanceOf(SyncopeGroup::class, $group);

    // Delete the role and make sure it's gone in Syncope.
    $role->delete();
    try {
      $this->getClient()->getGroup($uuid);
      $this->fail('The group was found and should not be');
    }
    catch (\Exception $exception) {
      $this->assertInstanceOf(SyncopeGroupNotFoundException::class, $exception);
    }
  }

  /**
   * Tests that global roles do no get created in Syncope.
   */
  public function testGlobalRoles(): void {
    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->container->get('entity_type.manager')->getStorage('user_role')
      ->create(['id' => 'my_global_role', 'label' => 'My Global Role']);
    $role->setThirdPartySetting('oe_authorisation', 'global', TRUE);
    $role->save();

    // Assert no role got created.
    try {
      $this->getClient()->getGroup($role->id(), SyncopeClient::IDENTIFIER_NAME);
      $this->fail('The group was found and should not be');
    }
    catch (\Exception $exception) {
      $this->assertInstanceOf(SyncopeGroupNotFoundException::class, $exception);
    }
  }

}
