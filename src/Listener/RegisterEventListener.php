<?php

namespace App\Listener;

use App\Event\ExistingAccountRegistrationEvent;
use App\Event\UserRegisteredEvent;
use App\Service\EmailService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class RegisterEventListener
{
    public function __construct(private EmailService $emailService) {}

    #[AsEventListener]
    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $this->emailService->sendVerificationMail($event->user);
    }

    #[AsEventListener]
    public function onExistingAccountRegistration(ExistingAccountRegistrationEvent $event): void
    {
        $this->emailService->sendAccountExistsMail($event->email);
    }
}