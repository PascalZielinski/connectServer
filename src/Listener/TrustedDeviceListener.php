<?php

namespace App\EventListener;

use App\Entity\TrustedDevice;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request', method: 'onKernelRequest', priority: 20)]
final readonly class TrustedDeviceLoginListener
{
    private const LOGIN_PATH     = '/api/login';
    private const DEVICE_HEADER  = 'X-Device-Id';

    private const USERNAME_KEYS  = ['username', 'email', '_username'];

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getPathInfo() !== self::LOGIN_PATH || !$request->isMethod('POST')) {
            return;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return;
        }

        $username = null;
        foreach (self::USERNAME_KEYS as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                $username = $data[$key];
                break;
            }
        }
        if ($username === null) {
            return;
        }

        $deviceId = $request->headers->get(self::DEVICE_HEADER);
        if (!$deviceId) {

            $user = $this->findUser($username);
            if ($user !== null && $this->userHasDevices($user)) {
                $this->deny($event, 'Missing ' . self::DEVICE_HEADER . ' header', 'DEVICE_HEADER_MISSING', 400);
            }
            return;
        }

        $user = $this->findUser($username);
        if ($user === null) {

            return;
        }

        if (!$this->userHasDevices($user)) {

            return;
        }

        if (!$this->deviceIsRegistered($user, $deviceId)) {
            $this->deny($event, 'Device not registered', 'DEVICE_NOT_TRUSTED', 403);
        }
    }

    private function findUser(string $username): ?User
    {
        // ⚠️ Passe das Feld an deine User-Entity an (email / username / …)
        return $this->em->getRepository(User::class)->findOneBy(['email' => $username]);
    }

    private function userHasDevices(User $user): bool
    {
        return $this->em->getRepository(TrustedDevice::class)
            ->count(['User' => $user]) > 0;
    }

    private function deviceIsRegistered(User $user, string $deviceId): bool
    {
        foreach ($this->em->getRepository(TrustedDevice::class)->findBy(['User' => $user]) as $device) {
            if ((string) $device->getDeviceUuid() === $deviceId) {
                return true;
            }
        }

        return false;
    }

    private function deny(RequestEvent $event, string $message, string $code, int $status): void
    {
        $event->setResponse(new JsonResponse([
            'error' => $message,
            'code'  => $code,
        ], $status));
    }
}