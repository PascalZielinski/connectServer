<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ExistingAccountRegistrationEvent extends Event
{
    public function __construct(public readonly string $email) {}
}