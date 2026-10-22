<?php

namespace App\Service;

use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Repository\TrustedDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final readonly class TrustedDeviceService
{
    private const CONFIRMATION_TTL = '+15 minutes';
    private const TRUST_TTL = '+30 days';

    public function __construct(
        private TrustedDeviceRepository $devices,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $mailFrom,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function isTrusted(User $user, string $deviceSecret, ?string $userAgent = null): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $hash = hash('sha256', $deviceSecret);
        $device = $this->devices->findOneBy(['User' => $user, 'deviceSecretHash' => $hash]);

        if ($device !== null && $device->getExpiredAt() > $now) {
            if (!$device->isPending()) {
                $device->setLastLoginAt($now);
                $this->entityManager->flush();

                return true;
            }

            return false;
        }

        if ($this->devices->countPendingSince($user, $now->modify('-1 hour')) >= 3) {
            return false;
        }

        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $userAgent ?? '') ?: 'Unbekanntes Gerät';

        $device ??= (new TrustedDevice())
            ->setUser($user)
            ->setDeviceSecretHash($hash);

        $device
            ->setDeviceName(mb_substr($name, 0, 255));

        $token = bin2hex(random_bytes(32));
        $device->startConfirmation(hash('sha256', $token), $now->modify(self::CONFIRMATION_TTL));

        $this->entityManager->persist($device);
        $this->entityManager->flush();

        try {
            $this->sendWarningMail($user, $device, $token);
        } catch (TransportExceptionInterface $e) {

        }

        return false;
    }

    public function confirm(string $token): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $device = $this->devices->findOneBy(['confirmationTokenHash' => hash('sha256', $token)]);

        if ($device === null || !$device->isPending() || $device->getExpiredAt() < $now) {
            return false;
        }

        $device->confirm($now->modify(self::TRUST_TTL));
        $this->entityManager->flush();

        return true;
    }

    private function sendWarningMail(User $user, TrustedDevice $device, string $token): void
    {
        $link = rtrim($this->frontendUrl, '/') . '/device/confirm?token=' . $token;

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($user->getEmail())
            ->subject('Warnung: Anmeldeversuch von unbekanntem Gerät')
            ->text(sprintf(
                "Es gab einen Anmeldeversuch auf dein Konto von einem unbekannten Gerät:\n%s\n\n"
                . "Warst du das? Dann bestätige das Gerät (Link 15 Minuten gültig):\n%s\n\n"
                . "Warst du das nicht? Ignoriere diese Mail und ändere sofort dein Passwort. "
                . "Ohne deine Bestätigung bleibt der Login blockiert.",
                $device->getDeviceName(),
                $link,
            ));

        $this->mailer->send($email);
    }
}