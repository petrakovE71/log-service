<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LogIngestionResult;
use App\Message\ProcessLogBatchMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class LogIngestionService
{
    public function __construct(
        private readonly LogValidator $validator,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<mixed> $logs Raw log payload (array of log entries)
     */
    public function ingest(array $logs): LogIngestionResult
    {
        $entries = $this->validator->validate($logs);

        $batchId = 'batch_' . str_replace('-', '', Uuid::v4()->toRfc4122());
        $publishedAt = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        $logData = array_map(fn ($entry) => [
            'timestamp' => $entry->timestamp,
            'level' => $entry->level->value,
            'service' => $entry->service,
            'message' => $entry->message,
            'context' => $entry->context,
            'trace_id' => $entry->traceId,
        ], $entries);

        try {
            $this->bus->dispatch(new ProcessLogBatchMessage($logData, $batchId, $publishedAt));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to dispatch log batch', [
                'batch_id' => $batchId,
                'total' => count($entries),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return new LogIngestionResult($batchId, count($entries));
    }
}
