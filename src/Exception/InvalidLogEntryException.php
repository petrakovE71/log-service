<?php

declare(strict_types=1);

namespace App\Exception;

final class InvalidLogEntryException extends \InvalidArgumentException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
