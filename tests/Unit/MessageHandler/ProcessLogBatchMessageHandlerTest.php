<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\ProcessLogBatchMessage;
use App\MessageHandler\ProcessLogBatchMessageHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProcessLogBatchMessageHandlerTest extends TestCase
{
    private LoggerInterface $logger;
    private ProcessLogBatchMessageHandler $handler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new ProcessLogBatchMessageHandler($this->logger);
    }

    #[Test]
    public function it_logs_each_entry_in_the_batch(): void
    {
        $logs = [
            $this->logEntry(['message' => 'First']),
            $this->logEntry(['message' => 'Second']),
            $this->logEntry(['message' => 'Third']),
        ];

        $message = new ProcessLogBatchMessage($logs, 'batch_abc123', '2026-03-04T12:00:00+00:00');

        $this->logger
            ->expects(self::exactly(3))
            ->method('info');

        ($this->handler)($message);
    }

    #[Test]
    public function it_passes_correct_context_to_logger(): void
    {
        $logs = [
            $this->logEntry([
                'level' => 'error',
                'service' => 'payment-service',
                'trace_id' => 'trace-xyz',
            ]),
        ];

        $message = new ProcessLogBatchMessage($logs, 'batch_def456', '2026-03-04T12:00:00+00:00');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Processing log entry', [
                'batch_id' => 'batch_def456',
                'level' => 'error',
                'service' => 'payment-service',
                'trace_id' => 'trace-xyz',
            ]);

        ($this->handler)($message);
    }

    #[Test]
    public function it_logs_null_trace_id_when_absent(): void
    {
        $logs = [
            $this->logEntry(['trace_id' => null]),
        ];

        $message = new ProcessLogBatchMessage($logs, 'batch_ghi789', '2026-03-04T12:00:00+00:00');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Processing log entry', self::callback(
                fn (array $context) => $context['trace_id'] === null,
            ));

        ($this->handler)($message);
    }

    #[Test]
    public function it_does_not_log_when_batch_is_empty(): void
    {
        $message = new ProcessLogBatchMessage([], 'batch_empty', '2026-03-04T12:00:00+00:00');

        $this->logger
            ->expects(self::never())
            ->method('info');

        ($this->handler)($message);
    }

    private function logEntry(array $overrides = []): array
    {
        return array_merge([
            'timestamp' => '2026-03-04T12:00:00+00:00',
            'level' => 'info',
            'service' => 'test-service',
            'message' => 'Test log message',
            'context' => [],
            'trace_id' => null,
        ], $overrides);
    }
}
