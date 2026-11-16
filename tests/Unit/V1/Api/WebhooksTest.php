<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\V1\Api;

use Breezedoc\Exceptions\ValidationException;
use Breezedoc\Tests\Unit\V1\TeamApiTestCase;
use Breezedoc\V1\Api\Webhooks;
use Breezedoc\V1\Models\WebhookDelivery;
use Breezedoc\V1\Models\WebhookEndpoint;
use Breezedoc\Webhooks\WebhookEventType;
use InvalidArgumentException;

class WebhooksTest extends TeamApiTestCase
{
    private Webhooks $webhooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webhooks = new Webhooks($this->httpClient, $this->requestBuilder);
    }

    public function testList(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [
                ['id' => 'wh_1', 'url' => 'https://example.com/hook', 'events' => ['document.completed']],
            ],
        ]));

        $endpoints = $this->webhooks->list();

        $this->assertCount(1, $endpoints);
        $this->assertInstanceOf(WebhookEndpoint::class, $endpoints[0]);
        $this->assertSame('wh_1', $endpoints[0]->getId());
        $this->assertSame(['document.completed'], $endpoints[0]->getEvents());
        $this->assertNull($endpoints[0]->getSecret());
        $this->assertSame('https://breezedoc.com/api/v1/webhooks', (string) $this->lastRequest()->getUri());
    }

    public function testListAcceptsATopLevelArray(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([['id' => 'wh_1'], ['id' => 'wh_2']]));

        $this->assertCount(2, $this->webhooks->list());
    }

    public function testCreateReturnsTheSecretOnce(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 'wh_1',
            'url' => 'https://example.com/hook',
            'events' => WebhookEventType::subscribable(),
            'secret' => 'whsec_abc',
        ], 201));

        $endpoint = $this->webhooks->create('https://example.com/hook', WebhookEventType::subscribable(), 'wh-create-1');

        $this->assertSame('whsec_abc', $endpoint->getSecret());
        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('wh-create-1', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame(
            ['url' => 'https://example.com/hook', 'events' => WebhookEventType::subscribable()],
            $this->jsonBody($request)
        );
    }

    public function testCreateValidatesInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->webhooks->create('https://example.com/hook', []);
    }

    public function testCreateRejectsNonStringEvents(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->webhooks->create('https://example.com/hook', ['document.completed', 5]);
    }

    public function testCreateInvalidDestination(): void
    {
        $this->httpClient->addResponse($this->createEnvelopeError('invalid_destination', 'Use HTTPS on port 443.', 422));

        try {
            $this->webhooks->create('http://localhost:8080/hook', ['document.completed']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('invalid_destination', $e->getErrorCode());
        }
    }

    public function testDestroy(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([]));

        $this->webhooks->destroy('wh_1');

        $request = $this->lastRequest();
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('https://breezedoc.com/api/v1/webhooks/wh_1', (string) $request->getUri());
    }

    public function testTestQueuesAnEvent(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['status' => 'queued'], 202));

        $result = $this->webhooks->test('wh_1', 'test-1');

        $this->assertSame(['status' => 'queued'], $result);
        $request = $this->lastRequest();
        $this->assertSame('https://breezedoc.com/api/v1/webhooks/wh_1/test', (string) $request->getUri());
        $this->assertSame('test-1', $request->getHeaderLine('Idempotency-Key'));
    }

    public function testRotateSecret(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 'wh_1', 'signing_secret' => 'whsec_new']));

        $endpoint = $this->webhooks->rotateSecret('wh_1');

        $this->assertSame('whsec_new', $endpoint->getSecret());
        $this->assertSame('https://breezedoc.com/api/v1/webhooks/wh_1/rotate', (string) $this->lastRequest()->getUri());
    }

    public function testDeliveriesAndRedeliver(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [['id' => 'dlv_1', 'created_at' => '2026-11-16T09:00:00Z']],
        ]));
        $this->httpClient->addResponse($this->createJsonResponse([], 202));

        $deliveries = $this->webhooks->deliveries('wh_1');
        $this->webhooks->redeliver('wh_1', $deliveries[0]->getId(), 'redeliver-1');

        $this->assertInstanceOf(WebhookDelivery::class, $deliveries[0]);
        $this->assertSame('dlv_1', $deliveries[0]->getId());
        $requests = $this->httpClient->getRequests();
        $this->assertSame('https://breezedoc.com/api/v1/webhooks/wh_1/deliveries', (string) $requests[0]->getUri());
        $this->assertSame(
            'https://breezedoc.com/api/v1/webhooks/wh_1/deliveries/dlv_1/redeliver',
            (string) $requests[1]->getUri()
        );
        $this->assertSame('redeliver-1', $requests[1]->getHeaderLine('Idempotency-Key'));
    }
}
