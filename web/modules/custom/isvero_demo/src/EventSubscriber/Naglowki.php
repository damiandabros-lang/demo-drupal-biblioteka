<?php

namespace Drupal\isvero_demo\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Nagłówki bezpieczeństwa i zakaz indeksowania wersji demonstracyjnej.
 */
class Naglowki implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -10]];
  }

  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $h = $event->getResponse()->headers;
    if (getenv('ISV_ALLOW_INDEX') !== '1') {
      $h->set('X-Robots-Tag', 'noindex, nofollow');
    }
    $h->set('X-Content-Type-Options', 'nosniff');
    $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
  }

}
