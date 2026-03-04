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
