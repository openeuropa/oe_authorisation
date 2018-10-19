<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authorisation\Behat;

use Behat\Mink\Element\NodeElement;
use Drupal\DrupalExtension\Context\DrupalContext;

/**
 * Behat feature context file which contains specific steps for authorisation.
 */
class FeatureContext extends DrupalContext {

  /**
   * Checks that a 200 OK response occurred.
   *
   * @Then I should be able to access the page
   */
  public function assertSuccessfulResponse() {
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Checks that a 403 Access Denied error occurred.
   *
   * @Then I should not be able to access the page
   */
  public function assertAccessDenied() {
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Asserts that a given user has a given role.
   *
   * @Then the user :name should have the role :role in Drupal
   */
  public function assertUserShouldHaveTheRole(string $name, string $role): void {
    $this->assertUserRole($name, $role, TRUE);
  }

  /**
   * Asserts that a given user does not have a role.
   *
   * @When the user :name does not have the role :role in Drupal
   * @Then the user :name should not have the role :role in Drupal
   */
  public function assertUserShouldNotHaveTheRole(string $name, string $role): void {
    $this->assertUserRole($name, $role, FALSE);
  }

  /**
   * Asserts that a user (by) name has or not a role in Drupal.
   *
   * @param string $name
   *   The username.
   * @param string $role
   *   The role.
   * @param bool $has
   *   Whether to check if the user has or does not have those roles.
   *
   * @throws \Exception
   */
  protected function assertUserRole(string $name, string $role, bool $has): void {
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $user_storage->resetCache();
    $users = $user_storage->loadByProperties(['name' => $name]);
    if (!$users) {
      throw new \Exception(sprintf('User %s not found', $name));
    }

    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadByProperties(['label' => $role]);
    if (!$roles) {
      throw new \Exception(sprintf('The requested role %s could not be found', $role));
    }
    $role = reset($roles);

    /** @var \Drupal\user\UserInterface $user */
    $user = reset($users);
    if ($has && !$user->hasRole($role->id())) {
      throw new \Exception(sprintf('The user %s does not have the role %s.', $name, $role->label()));
    }

    if (!$has && $user->hasRole($role->id())) {
      throw new \Exception(sprintf('The user %s has the role %s.', $name, $role->label()));
    }
  }

  /**
   * Asserts that the given role checkbox is disabled.
   *
   * @Then the :name role checkbox should be disabled
   *
   * @throws \Exception
   */
  public function assertRoleCheckboxDisabled(string $name): void {
    $session = $this->getSession();
    $element = $session->getPage()->findField($name);
    if (!$element instanceof NodeElement) {
      throw new \Exception(sprintf('%s role checkbox not found.', $name));
    }

    if ($element->getAttribute('disabled') !== 'disabled') {
      throw new \Exception(sprintf('%s role checkbox is not disabled.', $name));
    }
  }

}
