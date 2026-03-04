<?php

declare(strict_types=1);

namespace App\Message;

final class ProcessLogBatchMessage
{
    public function __construct(
        public readonly array $logs,
        public readonly string $batchId,
        public readonly string $publishedAt,
    ) {}
}
