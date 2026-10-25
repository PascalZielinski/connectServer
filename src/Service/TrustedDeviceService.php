<?php

namespace App\Service;

use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Repository\TrustedDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

final readonly class TrustedDeviceService
{
    private const CONFIRMATION_TTL = '+15 minutes';
    private const TRUST_TTL = '+30 days';

    public function __construct(
        private TrustedDeviceRepository $devices,
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $registry,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $mailFrom,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function isTrusted(User $user, string $deviceSecret): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $device = $this->devices->findOneBy([
            'user' => $user,
            'deviceSecretHash' => hash('sha256', $deviceSecret),
        ]);

        return $device !== null && $device->isTrusted($now);
    }

    public function confirm(string $token): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $device = $this->devices->findOneBy(['confirmationTokenHash' => hash('sha256', $token)]);

        if ($device === null || !$device->isPending() || $device->isConfirmationTokenExpired($now)) {
            return false;
        }

        $device->confirm($now->modify(self::TRUST_TTL));
        $this->entityManager->flush();

        return true;
    }

    public function recordLogin(User $user, string $deviceSecret, string $ip): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $device = $this->devices->findOneBy([
            'user' => $user,
            'deviceSecretHash' => hash('sha256', $deviceSecret),
        ]);

        if ($device === null || !$device->isTrusted($now)) {
            return;
        }

        $device->setIp($ip)->setAttemptAt($now)->setLastLoginAt($now);
        $this->entityManager->flush();
    }

    public function challenge(User $user, string $deviceSecret, string $ip, ?string $userAgent = null): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $hash = hash('sha256', $deviceSecret);
        $device = $this->devices->findOneBy(['user' => $user, 'deviceSecretHash' => $hash]);

        if ($device !== null) {
            $device->setIp($ip)->setAttemptAt($now);

            if ($device->isTrusted($now)) {
                $this->entityManager->flush();

                return;
            }

            if ($device->isPending() && !$device->isConfirmationTokenExpired($now)) {
                $this->entityManager->flush();

                return;
            }
        }

        if ($this->devices->countPendingSince($user, $now->modify('-1 hour')) >= 3) {
            if ($device !== null) {
                $this->entityManager->flush();
            }

            return;
        }

        $device ??= (new TrustedDevice())
            ->setUser($user)
            ->setDeviceSecretHash($hash);

        $device
            ->setDeviceName(UserAgentParser::describe($userAgent))
            ->setIp($ip)
            ->setAttemptAt($now);

        $token = bin2hex(random_bytes(32));
        $device->startConfirmation(hash('sha256', $token), $now->modify(self::CONFIRMATION_TTL));

        try {
            $this->entityManager->persist($device);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $this->registry->resetManager();

            return;
        }

        try {
            $this->sendWarningMail($user, $device, $token);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Device confirmation mail failed.', ['exception' => $e]);
        }
    }

    private function sendWarningMail(User $user, TrustedDevice $device, string $token): void
    {
        $link = rtrim($this->frontendUrl, '/') . '/device/confirm?token=' . $token;

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($user->getEmail())
            ->subject('Warnung: Anmeldeversuch von unbekanntem Gerät')
            ->text(sprintf(
                "Es gab einen Anmeldeversuch auf dein Konto:\n\n"
                . "Gerät: %s\n"
                . "IP: %s\n"
                . "Zeitpunkt: %s UTC\n\n"
                . "Warst du das? Dann bestätige das Gerät (Link 15 Minuten gültig):\n%s\n\n"
                . "Warst du das nicht? Ignoriere diese Mail und ändere sofort dein Passwort. "
                . "Ohne deine Bestätigung bleibt der Login blockiert.",
                $device->getDeviceName(),
                $device->getIp(),
                $device->getAttemptAt()?->format('d.m.Y H:i'),
                $link,
            ));

        $this->mailer->send($email);
    }
}