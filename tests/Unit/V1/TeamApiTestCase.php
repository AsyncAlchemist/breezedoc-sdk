<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\V1;

use Breezedoc\Config\Configuration;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestInterface;

/**
 * Base test case for Team API v1 resources (mocked HTTP only).
 */
abstract class TeamApiTestCase extends UnitTestCase
{
    protected MockHttpClient $httpClient;
    protected RequestBuilder $requestBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMockHttpClient();
        $factory = new Psr17Factory();
        $this->requestBuilder = RequestBuilder::forTeamApi(
            Configuration::forTeamApiKey('bd_live_test'),
            $factory,
            $factory
        );
    }

    protected function lastRequest(): RequestInterface
    {
        $requests = $this->httpClient->getRequests();
        $this->assertNotEmpty($requests);

        return $requests[count($requests) - 1];
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonBody(RequestInterface $request): array
    {
        $body = (string) $request->getBody();
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded, 'Request body is not a JSON object: ' . $body);

        return $decoded;
    }

    protected function createEnvelopeError(string $code, string $message, int $status): \Psr\Http\Message\ResponseInterface
    {
        return $this->createJsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
