<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\ApiExceptionListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

final class ApiExceptionListenerTest extends TestCase
{
    private ApiExceptionListener $listener;

    protected function setUp(): void
    {
        $this->listener = new ApiExceptionListener();
    }

    private function createEvent(\Throwable $exception, string $route = 'api_logs_ingest'): ExceptionEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }

    #[Test]
    public function it_ignores_exceptions_for_other_routes(): void
    {
        $event = $this->createEvent(new BadRequestHttpException(), 'other_route');

        ($this->listener)($event);

        self::assertNull($event->getResponse());
    }

    #[Test]
    public function it_converts_unsupported_media_type_to_400_with_content_type_message(): void
    {
        $event = $this->createEvent(new UnsupportedMediaTypeHttpException());

        ($this->listener)($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame('error', $body['status']);
        self::assertSame('Content-Type must be application/json.', $body['message']);
    }

    #[Test]
    public function it_converts_bad_request_with_not_encodable_to_400_with_json_message(): void
    {
        $previous = new NotEncodableValueException('Syntax error');
        $event = $this->createEvent(new BadRequestHttpException('', $previous));

        ($this->listener)($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame('error', $body['status']);
        self::assertSame('Invalid JSON: Syntax error', $body['message']);
    }

    #[Test]
    public function it_converts_bad_request_without_previous_to_400_with_logs_field_message(): void
    {
        $event = $this->createEvent(new BadRequestHttpException());

        ($this->listener)($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame('error', $body['status']);
        self::assertSame('Request body must contain a "logs" field.', $body['message']);
    }

    #[Test]
    public function it_converts_unprocessable_entity_to_400_with_logs_field_message(): void
    {
        $event = $this->createEvent(new UnprocessableEntityHttpException());

        ($this->listener)($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame('error', $body['status']);
        self::assertSame('Request body must contain a "logs" field.', $body['message']);
    }
}
