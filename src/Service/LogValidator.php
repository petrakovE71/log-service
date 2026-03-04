<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LogEntry;
use App\Exception\InvalidLogEntryException;
use App\Exception\LogIngestionException;
use App\Exception\ValidationException;
use App\Factory\LogEntryFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LogValidator
{
    private const MAX_BATCH_SIZE = 1000;

    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly LogEntryFactory $factory,
    ) {}

    /**
     * @return LogEntry[]
     *
     * @throws LogIngestionException
     * @throws ValidationException
     */
    public function validate(array $payload): array
    {
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

            try {
                $entry = $this->factory->create($item);
            } catch (InvalidLogEntryException $e) {
                $errors[] = [
                    'index' => $index,
                    'violations' => [['field' => $e->field, 'message' => $e->getMessage()]],
                ];
                continue;
            }

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
