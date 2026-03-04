<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\DTO\LogEntry;
use App\Enum\LogLevel;
use App\Exception\InvalidLogEntryException;
use App\Factory\LogEntryFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LogEntryFactoryTest extends TestCase
{
    private LogEntryFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new LogEntryFactory();
    }

    private static function validData(array $overrides = []): array
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
    public function it_creates_log_entry_from_valid_data(): void
    {
        $entry = $this->factory->create(self::validData());

        self::assertInstanceOf(LogEntry::class, $entry);
        self::assertSame('2026-03-04T12:00:00+00:00', $entry->timestamp);
        self::assertSame(LogLevel::Info, $entry->level);
        self::assertSame('test-service', $entry->service);
        self::assertSame('Test log message', $entry->message);
        self::assertSame(['env' => 'test'], $entry->context);
    }

    #[Test]
    public function it_throws_on_invalid_level(): void
    {
        $this->expectException(InvalidLogEntryException::class);
        $this->expectExceptionMessage('Field "level" must be a valid PSR-3 log level.');

        $this->factory->create(self::validData(['level' => 'INVALID']));
    }

    #[Test]
    public function it_throws_on_missing_level(): void
    {
        $data = self::validData();
        unset($data['level']);

        $this->expectException(InvalidLogEntryException::class);

        $this->factory->create($data);
    }

    #[Test]
    public function it_sets_trace_id_when_present(): void
    {
        $entry = $this->factory->create(self::validData(['trace_id' => 'abc123']));

        self::assertSame('abc123', $entry->traceId);
    }

    #[Test]
    public function it_sets_trace_id_to_null_when_absent(): void
    {
        $data = self::validData();
        unset($data['trace_id']);

        $entry = $this->factory->create($data);

        self::assertNull($entry->traceId);
    }

    #[Test]
    public function it_sets_trace_id_to_null_when_not_a_string(): void
    {
        $entry = $this->factory->create(self::validData(['trace_id' => 12345]));

        self::assertNull($entry->traceId);
    }

    #[Test]
    public function it_defaults_context_to_empty_array(): void
    {
        $data = self::validData();
        unset($data['context']);

        $entry = $this->factory->create($data);

        self::assertSame([], $entry->context);
    }

    #[Test]
    public function it_defaults_missing_fields_to_empty_strings(): void
    {
        $entry = $this->factory->create(['level' => 'error']);

        self::assertSame('', $entry->timestamp);
        self::assertSame('', $entry->service);
        self::assertSame('', $entry->message);
    }

    #[Test]
    public function it_exposes_field_name_on_exception(): void
    {
        try {
            $this->factory->create(self::validData(['level' => 'bad']));
            $this->fail('Expected InvalidLogEntryException');
        } catch (InvalidLogEntryException $e) {
            self::assertSame('level', $e->field);
        }
    }
}
