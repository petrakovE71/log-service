<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProcessLogMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessLogMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessLogMessage $message): void
    {
        $this->logger->info('Processing log entry', [
            'batch_id' => $message->batchId,
            'level' => $message->level,
            'service' => $message->service,
            'trace_id' => $message->traceId,
        ]);
    }
}
