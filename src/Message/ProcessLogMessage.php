<?php

declare(strict_types=1);

namespace App\Message;

final class ProcessLogMessage
{
    public function __construct(
        public readonly string $timestamp,
        public readonly string $level,
        public readonly string $service,
        public readonly string $message,
        public readonly array $context,
        public readonly string $batchId,
        public readonly string $publishedAt,
        public readonly ?string $traceId = null,
    ) {}
}
