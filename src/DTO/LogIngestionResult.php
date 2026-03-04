<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class LogIngestionResult
{
    public function __construct(
        public string $batchId,
        public int $logsCount,
    ) {}
}
