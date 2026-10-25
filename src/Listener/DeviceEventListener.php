<?php

namespace App\Listener;

use App\Event\DeviceChallengeStartedEvent;
use App\Service\EmailService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class DeviceEventListener
{
    public function __construct(private EmailService $emailService) {}

    #[AsEventListener]
    public function onDeviceChallengeStarted(DeviceChallengeStartedEvent $event): void
    {
        $this->emailService->sendLoginConfirmationMail(
            $event->user,
            $event->device,
            $event->confirmationToken,
        );
    }
}