<?php

declare(strict_types=1);

namespace App\Exception;

final class ValidationException extends LogIngestionException
{
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed for one or more log entries.',
    ) {
        parent::__construct($message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
