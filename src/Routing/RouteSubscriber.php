<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Site\Settings;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    // If role CRUD isn't disable don't change it.
    if (Settings::get('oe_authorisation_role_crud_enabled', FALSE)) {
      return;
    }

    $paths = [
      'user.role_add',
      'entity.user_role.edit_form',
      'entity.user_role.delete_form',
    ];

    foreach ($paths as $path) {
      $route = $collection->get($path);
      $route->setRequirement('_access', 'FALSE');
    }
  }

}
