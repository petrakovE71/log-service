<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\LogEntry;
use App\Enum\LogLevel;
use App\Exception\LogIngestionException;
use App\Exception\ValidationException;
use App\Factory\LogEntryFactory;
use App\Service\LogValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class LogValidatorTest extends TestCase
{
    private LogValidator $validator;

    protected function setUp(): void
    {
        $symfonyValidator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $this->validator = new LogValidator($symfonyValidator, new LogEntryFactory());
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

    // --- Happy path ---

    #[Test]
    public function it_accepts_a_valid_single_log_entry(): void
    {
        $entries = $this->validator->validate([self::validEntry()]);

        self::assertCount(1, $entries);
        self::assertInstanceOf(LogEntry::class, $entries[0]);
        self::assertSame(LogLevel::Info, $entries[0]->level);
        self::assertSame('test-service', $entries[0]->service);
    }

    #[Test]
    public function it_accepts_multiple_valid_entries(): void
    {
        $payload = [
            self::validEntry(['level' => 'error']),
            self::validEntry(['level' => 'debug']),
            self::validEntry(['level' => 'warning']),
        ];

        $entries = $this->validator->validate($payload);

        self::assertCount(3, $entries);
    }

    #[Test]
    public function it_accepts_a_batch_of_1000_entries(): void
    {
        $payload = array_fill(0, 1000, self::validEntry());

        $entries = $this->validator->validate($payload);

        self::assertCount(1000, $entries);
    }

    #[Test]
    public function it_accepts_entry_without_optional_context(): void
    {
        $entry = self::validEntry();
        unset($entry['context']);

        $entries = $this->validator->validate([$entry]);

        self::assertCount(1, $entries);
        self::assertSame([], $entries[0]->context);
    }

    #[Test]
    #[DataProvider('validLevelProvider')]
    public function it_accepts_all_valid_psr3_levels(string $level): void
    {
        $entries = $this->validator->validate([self::validEntry(['level' => $level])]);

        self::assertCount(1, $entries);
        self::assertSame(LogLevel::from($level), $entries[0]->level);
    }

    public static function validLevelProvider(): array
    {
        return [
            'debug'     => ['debug'],
            'info'      => ['info'],
            'notice'    => ['notice'],
            'warning'   => ['warning'],
            'error'     => ['error'],
            'critical'  => ['critical'],
            'alert'     => ['alert'],
            'emergency' => ['emergency'],
        ];
    }

    // --- Batch-level validation ---

    #[Test]
    public function it_rejects_empty_batch(): void
    {
        $this->expectException(LogIngestionException::class);
        $this->expectExceptionMessage('Batch must contain at least one log entry.');

        $this->validator->validate([]);
    }

    #[Test]
    public function it_rejects_batch_exceeding_1000_entries(): void
    {
        $payload = array_fill(0, 1001, self::validEntry());

        $this->expectException(LogIngestionException::class);
        $this->expectExceptionMessage('Batch exceeds maximum allowed size of 1000 logs.');

        $this->validator->validate($payload);
    }

    #[Test]
    public function it_rejects_non_array_entry(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(['not an object']);
    }

    // --- Field-level validation ---

    #[Test]
    #[DataProvider('missingRequiredFieldProvider')]
    public function it_rejects_entry_with_missing_required_field(string $fieldToRemove, string $expectedField): void
    {
        $entry = self::validEntry();
        unset($entry[$fieldToRemove]);

        try {
            $this->validator->validate([$entry]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);

            $fields = array_column($errors[0]['violations'], 'field');
            self::assertContains($expectedField, $fields);
        }
    }

    public static function missingRequiredFieldProvider(): array
    {
        return [
            'missing timestamp' => ['timestamp', 'timestamp'],
            'missing level'     => ['level', 'level'],
            'missing service'   => ['service', 'service'],
            'missing message'   => ['message', 'message'],
        ];
    }

    #[Test]
    public function it_rejects_invalid_log_level(): void
    {
        try {
            $this->validator->validate([self::validEntry(['level' => 'INVALID_LEVEL'])]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $fields = array_column($errors[0]['violations'], 'field');
            self::assertContains('level', $fields);
        }
    }

    #[Test]
    public function it_rejects_invalid_timestamp_format(): void
    {
        try {
            $this->validator->validate([self::validEntry(['timestamp' => 'not-a-date'])]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $fields = array_column($errors[0]['violations'], 'field');
            self::assertContains('timestamp', $fields);
        }
    }

    #[Test]
    public function it_rejects_service_exceeding_255_characters(): void
    {
        try {
            $this->validator->validate([self::validEntry(['service' => str_repeat('a', 256)])]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $fields = array_column($errors[0]['violations'], 'field');
            self::assertContains('service', $fields);
        }
    }

    #[Test]
    public function it_collects_errors_from_multiple_invalid_entries(): void
    {
        $payload = [
            self::validEntry(['timestamp' => '']),           // index 0: invalid
            self::validEntry(),                              // index 1: valid
            self::validEntry(['level' => 'bad_level']),      // index 2: invalid
        ];

        try {
            $this->validator->validate($payload);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(2, $errors);
            self::assertSame(0, $errors[0]['index']);
            self::assertSame(2, $errors[1]['index']);
        }
    }

    // --- trace_id ---

    #[Test]
    public function it_preserves_trace_id_when_present(): void
    {
        $entries = $this->validator->validate([
            self::validEntry(['trace_id' => 'abc123def456']),
        ]);

        self::assertSame('abc123def456', $entries[0]->traceId);
    }

    #[Test]
    public function it_sets_trace_id_to_null_when_absent(): void
    {
        $entry = self::validEntry();
        unset($entry['trace_id']);

        $entries = $this->validator->validate([$entry]);

        self::assertNull($entries[0]->traceId);
    }

    #[Test]
    public function it_returns_structured_error_with_message(): void
    {
        try {
            $this->validator->validate([self::validEntry(['level' => 'invalid'])]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('Validation failed for one or more log entries.', $e->getMessage());
            self::assertNotEmpty($e->getErrors());
        }
    }
}
