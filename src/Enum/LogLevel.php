<?php

declare(strict_types=1);

namespace App\Enum;

enum LogLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
    case Alert = 'alert';
    case Emergency = 'emergency';

    public function priority(): int
    {
        return match ($this) {
            self::Emergency, self::Alert, self::Critical => 9,
            self::Error => 7,
            self::Warning => 5,
            self::Notice, self::Info => 3,
            self::Debug => 1,
        };
    }
}
