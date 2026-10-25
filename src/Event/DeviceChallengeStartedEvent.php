<?php

namespace App\Event;

use App\Entity\TrustedDevice;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

final class DeviceChallengeStartedEvent extends Event
{
    public function __construct(
        public readonly User $user,
        public readonly TrustedDevice $device,
        public readonly string $confirmationToken,
    ) {}
}