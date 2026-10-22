<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $isApi = str_starts_with($event->getRequest()->getPathInfo(), '/api/');

        $headers->set('Cache-Control', 'no-store, max-age=0');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $headers->set('Content-Security-Policy', $isApi
            ? "default-src 'none'; frame-ancestors 'none'"
            : "default-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'"
        );

        $headers->remove('X-Powered-By');
    }
}