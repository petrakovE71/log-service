<?php

declare(strict_types=1);

namespace App\Message;

use App\DTO\LogEntry;

final class ProcessLogBatchMessage
{
    /**
     * @param LogEntry[] $logs
     */
    public function __construct(
        public readonly array $logs,
        public readonly string $batchId,
        public readonly string $publishedAt,
    ) {}
}
