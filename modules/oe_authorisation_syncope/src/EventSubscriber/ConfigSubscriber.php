<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope\EventSubscriber;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\oe_authorisation_syncope\Syncope\SyncopeRealm;
use Drupal\oe_authorisation_syncope\SyncopeClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to config events.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\oe_authorisation_syncope\SyncopeClient
   */
  protected $client;

  /**
   * ConfigSubscriber constructor.
   *
   * @param \Drupal\oe_authorisation_syncope\SyncopeClient $client
   */
  public function __construct(SyncopeClient $client) {
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[ConfigEvents::SAVE][] = 'onConfigSave';
    return $events;
  }

  /**
   * Callback for when a simple configuration object gets saved.
   *
   * We save the Syncope root and site realm UUIDs into the configuration when
   * the latter is first created.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config event.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    if ($config->getName() !== 'oe_authorisation_syncope.settings') {
      return;
    }

    // If this throws an exception the site needs to break. We cannot work
    // without the realm mapping.
    $realm = $this->client->getSiteRealm();
    if ($config->get('root_realm_uuid') === "") {
      $this->updateRealm($config, $realm);
      return;
    }

    if ($config->get('root_realm_uuid') !== $realm->getParent() || $config->get('site_realm_uuid') !== $realm->getUuid()) {
      $this->updateRealm($config, $realm);
      return;
    }

    // Otherwise we do nothing and we prevent a loop.
  }

  /**
   * Updates the realm from Syncope.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config object.
   * @param \Drupal\oe_authorisation_syncope\Syncope\SyncopeRealm $realm
   *   The Syncope realm.
   */
  protected function updateRealm(Config $config, SyncopeRealm $realm) {
    $config->set('root_realm_uuid', $realm->getParent());
    $config->set('site_realm_uuid', $realm->getUuid());
    $config->save();
  }

}
