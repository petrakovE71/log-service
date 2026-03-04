<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProcessLogBatchMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessLogBatchMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessLogBatchMessage $message): void
    {
        foreach ($message->logs as $log) {
            $this->logger->info('Processing log entry', [
                'batch_id' => $message->batchId,
                'level' => $log->level->value,
                'service' => $log->service,
                'trace_id' => $log->traceId,
            ]);
        }
    }
}
