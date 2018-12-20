<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope\EventSubscriber;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Messenger\Messenger;
use Drupal\oe_authorisation_syncope\Exception\SyncopeDownException;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Event subscriptions for events dispatched by KernelExceptions.
 */
class OeAuthorisationSyncopeSubscriber implements EventSubscriberInterface {

  /**
   * Constructor.
   *
   * We use dependency injection to get messenger service.
   *
   * @param Drupal\Core\Messenger\Messenger $messenger
   *   For getting Drupal messenger service.
   */
  public function __construct(Messenger $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['kernel.exception'] = ['handleSyncopeDownException'];
    return $events;
  }

  /**
   * Handles SyncopeDownExcpetion.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   Event with the associated exception.
   */
  public function handleSyncopeDownException(Event $event) {
    $exception = $event->getException();
    if ($exception instanceof SyncopeDownException ||
      ($exception instanceof EntityStorageException && $exception->getPrevious() instanceof SyncopeDownException)) {
      $this->messenger->addError($exception->getMessage());
      $response = new RedirectResponse(\Drupal::request()->getRequestUri());
      $response->send();
    }
  }

}
