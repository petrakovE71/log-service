<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\LogIngestionRequest;
use App\Exception\LogIngestionException;
use App\Exception\ValidationException;
use App\Service\LogIngestionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class LogIngestionController extends AbstractController
{
    public function __construct(
        private readonly LogIngestionService $ingestionService,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/api/logs/ingest', name: 'api_logs_ingest', methods: ['POST'])]
    public function ingest(#[MapRequestPayload] LogIngestionRequest $request): JsonResponse
    {
        try {
            $result = $this->ingestionService->ingest($request->logs);
        } catch (ValidationException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (LogIngestionException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('Log ingestion failed unexpectedly', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Internal server error. Please try again later.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json([
            'status' => 'accepted',
            'batch_id' => $result->batchId,
            'logs_count' => $result->logsCount,
        ], Response::HTTP_ACCEPTED);
    }
}
