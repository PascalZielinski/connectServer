<?php

namespace App\Service;

use App\Entity\User;
use App\Message\SendEmailMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use App\Entity\TrustedDevice;

final class EmailService
{
    public function __construct(
        private MessageBusInterface $bus,
        private VerifyEmailHelperInterface $verifyEmailHelper,
        #[Autowire(service: 'limiter.mail_verification')] private RateLimiterFactory $verificationLimiter,
        #[Autowire(service: 'limiter.mail_account_exists')] private RateLimiterFactory $accountExistsLimiter,
        #[Autowire(service: 'limiter.mail_account_exists_daily')] private RateLimiterFactory $accountExistsDailyLimiter,
        #[Autowire(service: 'limiter.login_confirm_mail')] private RateLimiterFactory $loginConfirmLimiter,
        #[Autowire(service: 'limiter.login_confirm_mail_daily')] private RateLimiterFactory $loginConfirmDailyLimiter,
        #[Autowire('%env(FRONTEND_URL)%')] private string $frontendUrl,
    ) {}

    public function sendVerificationMail(User $user): void
    {
        if (!$this->allow($this->verificationLimiter, (string) $user->getId())) {
            return;
        }

        $signature = $this->verifyEmailHelper->generateSignature(
            'app_verify_email',
            $user->getUuid(), 
            $user->getEmail(),
            ['uuid' => $user->getUuid()],
        );

        $this->bus->dispatch(new SendEmailMessage(
            $user->getEmail(),
            'Bitte bestätige deine E-Mail-Adresse',
            'email/register_verify.html.twig',
            [
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAt' => $signature->getExpiresAt()->format('d.m.Y H:i'),
            ],
        ));
    }

    public function sendAccountExistsMail(string $email): void
    {
        $key = hash('sha256', $email);

        if (
            !$this->allow($this->accountExistsLimiter, $key)
            || !$this->allow($this->accountExistsDailyLimiter, $key)
        ) {
            return;
        }

        $this->bus->dispatch(new SendEmailMessage(
            $email,
            'Registrierungsversuch mit deiner E-Mail-Adresse',
            'email/account_exists.html.twig',
        ));
    }

    public function sendLoginConfirmationMail(User $user, TrustedDevice $device, string $token): void
    {
        $key = (string) $user->getId();

        if (
            !$this->allow($this->loginConfirmLimiter, $key)
            || !$this->allow($this->loginConfirmDailyLimiter, $key)
        ) {
            return;
        }

        $this->bus->dispatch(new SendEmailMessage(
            $user->getEmail(),
            'Warnung: Anmeldeversuch von unbekanntem Gerät',
            'email/login_confirm.html.twig',
            [
                'device' => $device->getDeviceName(),
                'ip' => $device->getIp(),
                'attemptAt' => $device->getAttemptAt()?->format('d.m.Y H:i') . ' UTC',
                'link' => rtrim($this->frontendUrl, '/') . '/device/confirm?token=' . $token,
            ],
        ));
    }

    private function allow(RateLimiterFactory $factory, string $key): bool
    {
        return $factory->create($key)->consume()->isAccepted();
    }
}