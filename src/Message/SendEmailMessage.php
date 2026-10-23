<?php

namespace App\Message;

final class SendEmailMessage
{
    /** @param array<string, scalar|null> $context */
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $template,
        public readonly array $context = [],
    ) {}
}