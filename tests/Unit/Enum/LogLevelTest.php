<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\LogLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LogLevelTest extends TestCase
{
    #[Test]
    public function it_has_all_eight_psr3_levels(): void
    {
        $cases = LogLevel::cases();

        self::assertCount(8, $cases);
    }

    #[Test]
    #[DataProvider('levelPriorityProvider')]
    public function it_maps_level_to_correct_priority(LogLevel $level, int $expectedPriority): void
    {
        self::assertSame($expectedPriority, $level->priority());
    }

    public static function levelPriorityProvider(): array
    {
        return [
            'debug'     => [LogLevel::Debug, 1],
            'info'      => [LogLevel::Info, 3],
            'notice'    => [LogLevel::Notice, 3],
            'warning'   => [LogLevel::Warning, 5],
            'error'     => [LogLevel::Error, 7],
            'critical'  => [LogLevel::Critical, 9],
            'alert'     => [LogLevel::Alert, 9],
            'emergency' => [LogLevel::Emergency, 9],
        ];
    }

    #[Test]
    #[DataProvider('validLevelStringProvider')]
    public function it_creates_from_valid_string(string $value, LogLevel $expected): void
    {
        self::assertSame($expected, LogLevel::from($value));
    }

    public static function validLevelStringProvider(): array
    {
        return [
            'debug'     => ['debug', LogLevel::Debug],
            'info'      => ['info', LogLevel::Info],
            'notice'    => ['notice', LogLevel::Notice],
            'warning'   => ['warning', LogLevel::Warning],
            'error'     => ['error', LogLevel::Error],
            'critical'  => ['critical', LogLevel::Critical],
            'alert'     => ['alert', LogLevel::Alert],
            'emergency' => ['emergency', LogLevel::Emergency],
        ];
    }

    #[Test]
    public function it_returns_null_for_invalid_string(): void
    {
        self::assertNull(LogLevel::tryFrom('INVALID'));
        self::assertNull(LogLevel::tryFrom(''));
        self::assertNull(LogLevel::tryFrom('INFO'));
    }
}
