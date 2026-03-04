<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class LogIngestionRequest
{
    public function __construct(
        public array $logs,
    ) {}
}
