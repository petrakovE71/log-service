<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\LogLevel;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LogEntry
{
    public function __construct(
        #[Assert\NotBlank(message: 'Field "timestamp" is required.')]
        #[Assert\DateTime(format: \DateTimeInterface::ATOM, message: 'Field "timestamp" must be a valid ISO 8601 datetime.')]
        public string $timestamp,

        public LogLevel $level,

        #[Assert\NotBlank(message: 'Field "service" is required.')]
        #[Assert\Length(max: 255, maxMessage: 'Field "service" must not exceed 255 characters.')]
        public string $service,

        #[Assert\NotBlank(message: 'Field "message" is required.')]
        public string $message,

        public array $context = [],

        #[Assert\Length(max: 256, maxMessage: 'Field "trace_id" must not exceed 256 characters.')]
        #[SerializedName('trace_id')]
        public ?string $traceId = null,
    ) {}
}
