<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Api;

use Breezedoc\Api\AbstractApi;
use Breezedoc\Config\Configuration;
use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\AuthenticationException;
use Breezedoc\Exceptions\AuthorizationException;
use Breezedoc\Exceptions\ConflictException;
use Breezedoc\Exceptions\NotFoundException;
use Breezedoc\Exceptions\RateLimitException;
use Breezedoc\Exceptions\ValidationException;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Error handling for both envelopes: `{"message"}` (original API) and
 * `{"error": {"code", "message"}}` (Team API v1).
 */
class ErrorEnvelopeTest extends UnitTestCase
{
    private MockHttpClient $httpClient;
    private AbstractApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMockHttpClient();
        $factory = new Psr17Factory();
        $builder = new RequestBuilder(new Configuration('test-token'), $factory, $factory);
        $this->api = new class ($this->httpClient, $builder) extends AbstractApi {
            /**
             * @return array<string, mixed>
             */
            public function fetch(): array
            {
                return $this->get('/anything');
            }
        };
    }

    /**
     * @return array<string, array{int, class-string<ApiException>}>
     */
    public function envelopeStatusProvider(): array
    {
        return [
            '400' => [400, ApiException::class],
            '401' => [401, AuthenticationException::class],
            '403' => [403, AuthorizationException::class],
            '404' => [404, NotFoundException::class],
            '409' => [409, ConflictException::class],
            '422' => [422, ValidationException::class],
            '429' => [429, RateLimitException::class],
        ];
    }

    /**
     * @dataProvider envelopeStatusProvider
     * @param class-string<ApiException> $class
     */
    public function testTeamEnvelopeMapsStatusMessageAndCode(int $status, string $class): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(
            ['error' => ['code' => 'some_code', 'message' => 'Readable message']],
            $status
        ));

        try {
            $this->api->fetch();
            $this->fail('Expected ' . $class);
        } catch (ApiException $e) {
            $this->assertInstanceOf($class, $e);
            $this->assertSame($status, $e->getStatusCode());
            $this->assertSame('Readable message', $e->getMessage());
            $this->assertSame('some_code', $e->getErrorCode());
            $this->assertSame(
                ['error' => ['code' => 'some_code', 'message' => 'Readable message']],
                $e->getResponseBody()
            );
        }
    }

    public function testOriginalEnvelopeHasNoErrorCode(): void
    {
        $this->httpClient->addResponse($this->createErrorResponse('Unauthenticated.', 401));

        try {
            $this->api->fetch();
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame('Unauthenticated.', $e->getMessage());
            $this->assertNull($e->getErrorCode());
        }
    }

    public function testMessageFieldWinsOverEnvelope(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(
            ['message' => 'Top-level', 'error' => ['code' => 'c', 'message' => 'Nested']],
            400
        ));

        try {
            $this->api->fetch();
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('Top-level', $e->getMessage());
            $this->assertSame('c', $e->getErrorCode());
        }
    }

    public function testUnknownErrorWhenNoMessage(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['error' => 'not-an-object'], 500));

        try {
            $this->api->fetch();
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('Unknown error', $e->getMessage());
            $this->assertNull($e->getErrorCode());
        }
    }

    public function testConflictExceptionDefaults(): void
    {
        $e = new ConflictException();

        $this->assertSame(409, $e->getStatusCode());
        $this->assertSame('Conflict', $e->getMessage());
        $this->assertNull($e->getResponseBody());
        $this->assertNull($e->getErrorCode());
    }

    public function testExistingExceptionConstructorsStillWork(): void
    {
        $this->assertSame(401, (new AuthenticationException('x'))->getStatusCode());
        $this->assertSame(403, (new AuthorizationException('x'))->getStatusCode());
        $this->assertSame(404, (new NotFoundException('x'))->getStatusCode());
        $this->assertSame(['a' => ['b']], (new ValidationException('x', ['a' => ['b']]))->getErrors());
        $this->assertSame(5, (new RateLimitException('x', 5))->getRetryAfter());
    }
}
