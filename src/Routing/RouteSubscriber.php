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

    $routes = [
      'user.role_add',
      'entity.user_role.edit_form',
      'entity.user_role.delete_form',
    ];

    foreach ($routes as $route_name) {
      $route = $collection->get($route_name);
      $route->setRequirement('_superuser_access_check', 'TRUE');
    }
  }

}
