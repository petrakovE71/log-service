<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LogIngestionResult;
use App\Message\ProcessLogMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
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

        $dispatched = 0;

        foreach ($entries as $entry) {
            $message = new ProcessLogMessage(
                timestamp: $entry->timestamp,
                level: $entry->level->value,
                service: $entry->service,
                message: $entry->message,
                context: $entry->context,
                batchId: $batchId,
                publishedAt: $publishedAt,
                traceId: $entry->traceId,
            );

            $stamps = [];
            if (class_exists(AmqpStamp::class) && defined('AMQP_NOPARAM')) {
                $stamps[] = new AmqpStamp(null, AMQP_NOPARAM, ['priority' => $entry->level->priority()]);
            }

            try {
                $this->bus->dispatch($message, $stamps);
                $dispatched++;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to dispatch log message', [
                    'batch_id' => $batchId,
                    'dispatched' => $dispatched,
                    'total' => count($entries),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        return new LogIngestionResult($batchId, $dispatched);
    }
}
