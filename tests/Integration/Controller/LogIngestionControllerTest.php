<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Message\ProcessLogMessage;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class LogIngestionControllerTest extends WebTestCase
{
    private const INGEST_ENDPOINT = '/api/logs/ingest';
    private const HEALTH_ENDPOINT = '/api/health';

    private function validLog(array $overrides = []): array
    {
        return array_merge([
            'timestamp' => '2026-03-04T12:00:00+00:00',
            'level'     => 'info',
            'service'   => 'test-service',
            'message'   => 'Test log message',
            'context'   => ['env' => 'test'],
        ], $overrides);
    }

    private function validPayload(int $count = 1): array
    {
        return ['logs' => array_fill(0, $count, $this->validLog())];
    }

    // --- Health endpoint ---

    #[Test]
    public function health_endpoint_returns_200_with_ok_status(): void
    {
        $client = static::createClient();
        $client->request('GET', self::HEALTH_ENDPOINT);

        $response = $client->getResponse();
        $body = json_decode($response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $body['status']);
    }

    // --- 202 Accepted ---

    #[Test]
    public function ingest_returns_202_with_valid_single_log(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->validPayload(1))
        );

        $response = $client->getResponse();
        $body = json_decode($response->getContent(), true);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('accepted', $body['status']);
        self::assertSame(1, $body['logs_count']);
        self::assertMatchesRegularExpression('/^batch_[a-f0-9]{32}$/', $body['batch_id']);
    }

    #[Test]
    public function ingest_returns_202_with_batch_of_logs(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->validPayload(5))
        );

        $body = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(202, $client->getResponse()->getStatusCode());
        self::assertSame(5, $body['logs_count']);
    }

    // --- Message dispatch verification ---

    #[Test]
    public function each_log_is_dispatched_as_separate_message(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->validPayload(3))
        );

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');

        self::assertCount(3, $transport->getSent());
        self::assertInstanceOf(ProcessLogMessage::class, $transport->getSent()[0]->getMessage());
    }

    #[Test]
    public function dispatched_messages_contain_batch_metadata(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->validPayload(2))
        );

        $body = json_decode($client->getResponse()->getContent(), true);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = $transport->getSent();

        foreach ($messages as $envelope) {
            /** @var ProcessLogMessage $msg */
            $msg = $envelope->getMessage();
            self::assertSame($body['batch_id'], $msg->batchId);
            self::assertNotEmpty($msg->publishedAt);
        }
    }

    #[Test]
    public function dispatched_message_carries_log_data(): void
    {
        $client = static::createClient();
        $payload = ['logs' => [$this->validLog([
            'level'   => 'error',
            'service' => 'auth-service',
            'message' => 'Auth failed',
            'context' => ['user_id' => 42],
        ])]];

        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        /** @var ProcessLogMessage $msg */
        $msg = $transport->getSent()[0]->getMessage();

        self::assertSame('error', $msg->level);
        self::assertSame('auth-service', $msg->service);
        self::assertSame('Auth failed', $msg->message);
        self::assertSame(['user_id' => 42], $msg->context);
    }

    #[Test]
    public function dispatched_message_carries_trace_id(): void
    {
        $client = static::createClient();
        $payload = ['logs' => [$this->validLog(['trace_id' => 'trace-xyz-789'])]];

        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        /** @var ProcessLogMessage $msg */
        $msg = $transport->getSent()[0]->getMessage();

        self::assertSame('trace-xyz-789', $msg->traceId);
    }

    // --- 400 Bad Request ---

    #[Test]
    public function ingest_returns_400_for_payload_without_logs_key(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([['timestamp' => '2026-03-04T12:00:00+00:00', 'level' => 'info', 'service' => 'svc', 'message' => 'msg']])
        );

        $body = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('logs', $body['message']);
    }

    #[Test]
    public function ingest_returns_400_for_missing_content_type(): void
    {
        $client = static::createClient();
        $client->request('POST', self::INGEST_ENDPOINT, [], [], [], json_encode($this->validPayload()));

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function ingest_returns_400_for_malformed_json(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{not valid json}'
        );

        $body = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame('error', $body['status']);
    }

    #[Test]
    public function ingest_returns_400_for_empty_batch(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['logs' => []])
        );

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function ingest_returns_400_when_batch_exceeds_1000(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->validPayload(1001))
        );

        $body = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('1000', $body['message']);
    }

    #[Test]
    public function ingest_returns_400_with_validation_errors_for_invalid_entry(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['logs' => [[
                'timestamp' => 'invalid-date',
                'level'     => 'NOT_A_LEVEL',
                'service'   => '',
                'message'   => 'ok',
            ]]])
        );

        $body = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertArrayHasKey('errors', $body);
        self::assertNotEmpty($body['errors']);
        self::assertArrayHasKey('violations', $body['errors'][0]);
    }

    #[Test]
    public function no_messages_dispatched_on_validation_error(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            self::INGEST_ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['logs' => [[
                'timestamp' => '',
                'level'     => '',
                'service'   => '',
                'message'   => '',
            ]]])
        );

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');

        self::assertCount(0, $transport->getSent());
    }
}
