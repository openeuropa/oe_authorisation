<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Check whether a user has the uid 1.
 */
class SuperUserAccessCheck implements AccessInterface {

  /**
   * Checks access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if ($account->id() == 1) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
