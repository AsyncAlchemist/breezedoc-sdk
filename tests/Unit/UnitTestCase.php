<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit;

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Base test case for unit tests.
 *
 * Provides helpers for creating mock HTTP clients and responses.
 * Unit tests should never make live API requests.
 */
abstract class UnitTestCase extends TestCase
{
    /**
     * @var Psr17Factory
     */
    protected Psr17Factory $psr17Factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->psr17Factory = new Psr17Factory();
    }

    /**
     * Create a mock HTTP client.
     */
    protected function createMockHttpClient(): MockHttpClient
    {
        return new MockHttpClient();
    }

    /**
     * Create a mock JSON response.
     *
     * @param array<string, mixed> $data
     */
    protected function createJsonResponse(array $data, int $statusCode = 200): ResponseInterface
    {
        $body = $this->psr17Factory->createStream(json_encode($data));

        return $this->psr17Factory->createResponse($statusCode)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    /**
     * Create a mock error response.
     */
    protected function createErrorResponse(string $message, int $statusCode): ResponseInterface
    {
        return $this->createJsonResponse(['message' => $message], $statusCode);
    }

    /**
     * Create a validation error response (422).
     *
     * @param array<string, array<string>> $errors Field errors
     */
    protected function createValidationErrorResponse(string $message, array $errors): ResponseInterface
    {
        return $this->createJsonResponse([
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }
}
