<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LogEntry;
use App\Enum\LogLevel;
use App\Exception\LogIngestionException;
use App\Exception\ValidationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LogValidator
{
    private const MAX_BATCH_SIZE = 1000;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * @return LogEntry[]
     *
     * @throws LogIngestionException
     * @throws ValidationException
     */
    public function validate(mixed $payload): array
    {
        if (!is_array($payload)) {
            throw new LogIngestionException('Request body must be a JSON array.');
        }

        if (count($payload) === 0) {
            throw new LogIngestionException('Batch must contain at least one log entry.');
        }

        if (count($payload) > self::MAX_BATCH_SIZE) {
            throw new LogIngestionException(
                sprintf('Batch exceeds maximum allowed size of %d logs.', self::MAX_BATCH_SIZE),
            );
        }

        $entries = [];
        $errors = [];

        foreach ($payload as $index => $item) {
            if (!is_array($item)) {
                $errors[] = [
                    'index' => $index,
                    'error' => 'Each log entry must be a JSON object.',
                ];
                continue;
            }

            $level = LogLevel::tryFrom($item['level'] ?? '');

            if ($level === null) {
                $errors[] = [
                    'index' => $index,
                    'violations' => [
                        [
                            'field' => 'level',
                            'message' => 'Field "level" must be a valid PSR-3 log level.',
                        ],
                    ],
                ];
                continue;
            }

            $entry = new LogEntry(
                timestamp: $item['timestamp'] ?? '',
                level: $level,
                service: $item['service'] ?? '',
                message: $item['message'] ?? '',
                context: $item['context'] ?? [],
                traceId: isset($item['trace_id']) && is_string($item['trace_id']) ? $item['trace_id'] : null,
            );

            $violations = $this->validator->validate($entry);

            if (count($violations) > 0) {
                $entryErrors = [];
                foreach ($violations as $violation) {
                    $entryErrors[] = [
                        'field' => $violation->getPropertyPath(),
                        'message' => $violation->getMessage(),
                    ];
                }
                $errors[] = ['index' => $index, 'violations' => $entryErrors];
                continue;
            }

            $entries[] = $entry;
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $entries;
    }
}
