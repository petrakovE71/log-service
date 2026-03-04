<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

#[AsEventListener(event: 'kernel.exception', priority: 10)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->get('_route') !== 'api_logs_ingest') {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof UnsupportedMediaTypeHttpException) {
            $event->setResponse($this->errorResponse('Content-Type must be application/json.'));

            return;
        }

        if ($exception instanceof BadRequestHttpException) {
            $previous = $exception->getPrevious();

            if ($previous instanceof NotEncodableValueException) {
                $event->setResponse($this->errorResponse('Invalid JSON: ' . $previous->getMessage()));

                return;
            }

            $event->setResponse($this->errorResponse('Request body must contain a "logs" field.'));

            return;
        }

        if ($exception instanceof UnprocessableEntityHttpException) {
            $event->setResponse($this->errorResponse('Request body must contain a "logs" field.'));
        }
    }

    private function errorResponse(string $message): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => $message,
        ], Response::HTTP_BAD_REQUEST);
    }
}
