<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\LogEntry;
use App\Enum\LogLevel;
use App\Exception\InvalidLogEntryException;

final class LogEntryFactory
{
    public function create(array $data): LogEntry
    {
        $level = LogLevel::tryFrom($data['level'] ?? '');

        if ($level === null) {
            throw new InvalidLogEntryException('level', 'Field "level" must be a valid PSR-3 log level.');
        }

        return new LogEntry(
            timestamp: $data['timestamp'] ?? '',
            level: $level,
            service: $data['service'] ?? '',
            message: $data['message'] ?? '',
            context: $data['context'] ?? [],
            traceId: isset($data['trace_id']) && is_string($data['trace_id']) ? $data['trace_id'] : null,
        );
    }
}
