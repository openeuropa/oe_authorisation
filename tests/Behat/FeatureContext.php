<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authorisation\Behat;

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

}
