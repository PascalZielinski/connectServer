<?php

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class RateLimitSubscriber implements EventSubscriberInterface
{
    private const IP_ROUTES = [
        'api_login',
        'api_register',
        'api_users_create',
        'gesdinet_jwt_refresh_token',
    ];

    private const UPLOAD_ROUTES = [
        'api_profile_image_upload',
        'api_servers_image_update',
    ];

    public function __construct(
        private Security $security,
        #[Autowire(service: 'limiter.login')] private RateLimiterFactory $login,
        #[Autowire(service: 'limiter.register')] private RateLimiterFactory $register,
        #[Autowire(service: 'limiter.refresh')] private RateLimiterFactory $refresh,
        #[Autowire(service: 'limiter.anonymous_api')] private RateLimiterFactory $anonymous,
        #[Autowire(service: 'limiter.authenticated_api')] private RateLimiterFactory $authenticated,
        #[Autowire(service: 'limiter.chat_message')] private RateLimiterFactory $chatMessage,
        #[Autowire(service: 'limiter.chat_message_minute')] private RateLimiterFactory $chatMessageMinute,
        #[Autowire(service: 'limiter.upload')] private RateLimiterFactory $upload,
        #[Autowire(service: 'limiter.upload_daily')] private RateLimiterFactory $uploadDaily,
        #[Autowire(service: 'limiter.voice_token')] private RateLimiterFactory $voiceToken,
        #[Autowire('%kernel.environment%')]
        private readonly string $env,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onEarlyRequest', 20],
                ['onLateRequest', 0],
            ],
        ];
    }

    public function onEarlyRequest(RequestEvent $event): void
    {
        if (!$this->applies($event)) {
            return;
        }

        $request = $event->getRequest();

        $factory = match ($request->attributes->get('_route')) {
            'api_login' => $this->login,
            'gesdinet_jwt_refresh_token' => $this->refresh,
            'api_register', 'api_users_create' => $this->register,
            default => null,
        };

        if ($factory) {
            $this->consume($event, $factory, $request->getClientIp() ?? 'unknown');
        }
    }

    public function onLateRequest(RequestEvent $event): void
    {
        if (!$this->applies($event)) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (in_array($route, self::IP_ROUTES, true)) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user) {
            $this->consume($event, $this->anonymous, $request->getClientIp() ?? 'unknown');
            return;
        }

        $id = $user->getUserIdentifier();

        if (!$this->consume($event, $this->authenticated, $id)) {
            return;
        }

        if ($route === 'api_channel_messages_create') {
            $channel = (string) $request->attributes->get('channelUuid');

            if (
                !$this->consume($event, $this->chatMessage, $id . ':' . $channel)
                || !$this->consume($event, $this->chatMessageMinute, $id)
            ) {
                return;
            }
        }

        $isUpload = in_array($route, self::UPLOAD_ROUTES, true)
            || ($route === 'api_channel_messages_create' && $request->files->count() > 0);

        if ($isUpload) {
            if (
                !$this->consume($event, $this->upload, $id)
                || !$this->consume($event, $this->uploadDaily, $id)
            ) {
                return;
            }
        }

        if ($route === 'api_voice_channel_token') {
            $this->consume($event, $this->voiceToken, $id);
        }
    }

    private function applies(RequestEvent $event): bool
    {
        if ('test' === $this->env) {
            return false;
        }

        if (!$event->isMainRequest() || $event->hasResponse()) {
            return false;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        return $route !== null
            && !str_starts_with($route, '_')
            && !$request->isMethod('OPTIONS');
    }

    private function consume(RequestEvent $event, RateLimiterFactory $factory, string $key): bool
    {
        $limit = $factory->create($key)->consume();

        if ($limit->isAccepted()) {
            return true;
        }

        $retry = max(1, $limit->getRetryAfter()->getTimestamp() - time());

        $event->setResponse(new JsonResponse(
            ['error' => 'rate_limited', 'retry_after' => $retry],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $retry],
        ));

        return false;
    }
}