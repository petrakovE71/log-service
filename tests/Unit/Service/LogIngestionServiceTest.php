<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\LogIngestionResult;
use App\Exception\LogIngestionException;
use App\Exception\ValidationException;
use App\Message\ProcessLogMessage;
use App\Service\LogIngestionService;
use App\Service\LogValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validation;

final class LogIngestionServiceTest extends TestCase
{
    private MessageBusInterface $bus;
    private LogIngestionService $service;

    protected function setUp(): void
    {
        $symfonyValidator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $validator = new LogValidator($symfonyValidator);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->service = new LogIngestionService($validator, $this->bus, new NullLogger());
    }

    private static function validEntry(array $overrides = []): array
    {
        return array_merge([
            'timestamp' => '2026-03-04T12:00:00+00:00',
            'level'     => 'info',
            'service'   => 'test-service',
            'message'   => 'Test log message',
            'context'   => ['env' => 'test'],
        ], $overrides);
    }

    #[Test]
    public function it_returns_ingestion_result_on_success(): void
    {
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(fn ($message, $stamps) => new Envelope($message, $stamps));

        $result = $this->service->ingest([self::validEntry()]);

        self::assertInstanceOf(LogIngestionResult::class, $result);
        self::assertSame(1, $result->logsCount);
        self::assertMatchesRegularExpression('/^batch_[a-f0-9]{32}$/', $result->batchId);
    }

    #[Test]
    public function it_dispatches_correct_number_of_messages(): void
    {
        $dispatched = [];
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) use (&$dispatched) {
                $dispatched[] = $message;
                return new Envelope($message, $stamps);
            });

        $result = $this->service->ingest([
            self::validEntry(['level' => 'error']),
            self::validEntry(['level' => 'info']),
            self::validEntry(['level' => 'debug']),
        ]);

        self::assertSame(3, $result->logsCount);
        self::assertCount(3, $dispatched);
        self::assertInstanceOf(ProcessLogMessage::class, $dispatched[0]);
    }

    #[Test]
    public function it_sets_correct_level_and_batch_id_on_messages(): void
    {
        $dispatched = [];
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) use (&$dispatched) {
                $dispatched[] = $message;
                return new Envelope($message, $stamps);
            });

        $result = $this->service->ingest([self::validEntry(['level' => 'error'])]);

        /** @var ProcessLogMessage $msg */
        $msg = $dispatched[0];
        self::assertSame('error', $msg->level);
        self::assertSame($result->batchId, $msg->batchId);
        self::assertNotEmpty($msg->publishedAt);
    }

    #[Test]
    public function it_passes_trace_id_to_message(): void
    {
        $dispatched = [];
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) use (&$dispatched) {
                $dispatched[] = $message;
                return new Envelope($message, $stamps);
            });

        $this->service->ingest([self::validEntry(['trace_id' => 'trace-abc-123'])]);

        /** @var ProcessLogMessage $msg */
        $msg = $dispatched[0];
        self::assertSame('trace-abc-123', $msg->traceId);
    }

    #[Test]
    public function it_sets_null_trace_id_when_absent(): void
    {
        $dispatched = [];
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) use (&$dispatched) {
                $dispatched[] = $message;
                return new Envelope($message, $stamps);
            });

        $this->service->ingest([self::validEntry()]);

        /** @var ProcessLogMessage $msg */
        $msg = $dispatched[0];
        self::assertNull($msg->traceId);
    }

    #[Test]
    public function it_rethrows_dispatch_failure(): void
    {
        $this->bus
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->service->ingest([self::validEntry()]);
    }

    #[Test]
    public function it_throws_validation_exception_for_invalid_entries(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->ingest([self::validEntry(['level' => 'INVALID'])]);
    }

    #[Test]
    public function it_throws_log_ingestion_exception_for_empty_batch(): void
    {
        $this->expectException(LogIngestionException::class);
        $this->expectExceptionMessage('Batch must contain at least one log entry.');

        $this->service->ingest([]);
    }
}
