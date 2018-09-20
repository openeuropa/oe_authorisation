<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authorisation\Behat;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\oe_authorisation_syncope\Syncope\SyncopeUser;
use Drupal\oe_authorisation_syncope\SyncopeClient;

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
   * The list of Syncope users to clear.
   *
   * @var \Drupal\oe_authorisation_syncope\Syncope\SyncopeUser[]
   */
  protected $syncopeUsers = [];

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

      if (\Drupal::moduleHandler()->moduleExists('oe_authorisation_syncope')) {
        // We need to create a global user as well.
        $syncope_user = new SyncopeUser('', $user->name, []);
        $this->syncopeUsers[] = $this->getSyncopeClient()->createUser($syncope_user, 'root');
        // Then add the global roles.
        $this->getSyncopeClient()->addGlobalRoles($user->name, $roles);
      }

      // Login.
      $this->login($user);
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
  public function cleanUpSyncopeUsers(AfterScenarioScope $afterScenarioScope) {
    foreach ($this->syncopeUsers as $user) {
      $this->getSyncopeClient()->deleteUser($user->getUuid());
    }
  }

}
