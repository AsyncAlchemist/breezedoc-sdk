<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Http;

use Breezedoc\Exceptions\RateLimitException;
use Breezedoc\Http\RateLimitHandler;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RateLimitHandlerTest extends UnitTestCase
{
    private MockHttpClient $httpClient;
    private RateLimitHandler $handler;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = new MockHttpClient();
        $this->handler = new RateLimitHandler($this->httpClient, 3);
        $this->factory = new Psr17Factory();
    }

    public function testSuccessfulRequestPassesThrough(): void
    {
        $request = $this->createRequest();
        $expectedResponse = $this->createJsonResponse(['success' => true]);
        $this->httpClient->addResponse($expectedResponse);

        $response = $this->handler->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRetriesOn429Response(): void
    {
        $request = $this->createRequest();

        // First request returns 429, second returns 200
        $this->httpClient->addResponse($this->create429Response(1));
        $this->httpClient->addResponse($this->createJsonResponse(['success' => true]));

        $response = $this->handler->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRetriesMultipleTimesOn429(): void
    {
        $request = $this->createRequest();

        // First two requests return 429, third returns 200
        $this->httpClient->addResponse($this->create429Response(1));
        $this->httpClient->addResponse($this->create429Response(1));
        $this->httpClient->addResponse($this->createJsonResponse(['success' => true]));

        $response = $this->handler->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testThrowsExceptionAfterMaxRetries(): void
    {
        $handler = new RateLimitHandler($this->httpClient, 2);
        $request = $this->createRequest();

        // All requests return 429
        $this->httpClient->addResponse($this->create429Response(1));
        $this->httpClient->addResponse($this->create429Response(1));
        $this->httpClient->addResponse($this->create429Response(1));

        $this->expectException(RateLimitException::class);
        $handler->sendRequest($request);
    }

    public function testZeroRetriesThrowsImmediately(): void
    {
        $handler = new RateLimitHandler($this->httpClient, 0);
        $request = $this->createRequest();

        $this->httpClient->addResponse($this->create429Response(1));

        $this->expectException(RateLimitException::class);
        $handler->sendRequest($request);
    }

    public function testNon429ErrorsPassThrough(): void
    {
        $request = $this->createRequest();
        $this->httpClient->addResponse($this->createJsonResponse(['error' => 'Not found'], 404));

        $response = $this->handler->sendRequest($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    private function createRequest(): RequestInterface
    {
        return $this->factory->createRequest('GET', 'https://api.example.com/test');
    }

    private function create429Response(int $retryAfter = 60): ResponseInterface
    {
        $body = $this->factory->createStream(json_encode(['message' => 'Too Many Attempts.']));
        return $this->factory->createResponse(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string) $retryAfter)
            ->withBody($body);
    }
}
