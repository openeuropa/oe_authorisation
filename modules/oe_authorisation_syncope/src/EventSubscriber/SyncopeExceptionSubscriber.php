<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope\EventSubscriber;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Messenger\Messenger;
use Drupal\oe_authorisation_syncope\Exception\SyncopeDownException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriptions for events dispatched by KernelExceptions.
 */
class SyncopeExceptionSubscriber implements EventSubscriberInterface {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Constructs a new SyncopeExceptionSubscriber.
   *
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   */
  public function __construct(Messenger $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION] = ['handleSyncopeDownException'];
    return $events;
  }

  /**
   * Handles the cases when Syncope is down and exception is thrown for this.
   *
   * We set a message and redirect in case it happens during a user interaction.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The exception event.
   */
  public function handleSyncopeDownException(GetResponseForExceptionEvent $event) {
    $exception = $event->getException();
    if (!$this->isSyncopeDownException($exception)) {
      return;
    }

    $this->messenger->addError($exception->getMessage());
    $event->setResponse(new RedirectResponse($event->getRequest()->getRequestUri()));
  }

  /**
   * Checks whether the exception is one that indicates that Syncope is down.
   *
   * Typically this will be an instance of SyncopeDownException.
   *
   * It can also happen that entity storage exception is thrown after a
   * SyncopeDownException.
   *
   * @param \Exception $exception
   *   The exception.
   *
   * @return bool
   *   Whether we should treat this as a Syncope down situation.
   */
  protected function isSyncopeDownException(\Exception $exception) {
    if ($exception instanceof SyncopeDownException) {
      return TRUE;
    }

    if ($exception instanceof EntityStorageException && $exception->getPrevious() instanceof SyncopeDownException) {
      return TRUE;
    }

    return FALSE;
  }

}
