<?php

namespace App\Service;

final readonly class OperationResult
{
    public function __construct(
        public mixed $data,
        public int $statusCode = 200,
    ) {
    }
}
