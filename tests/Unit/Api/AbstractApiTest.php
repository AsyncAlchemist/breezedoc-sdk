<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Api;

use Breezedoc\Api\AbstractApi;
use Breezedoc\Config\Configuration;
use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\AuthenticationException;
use Breezedoc\Exceptions\AuthorizationException;
use Breezedoc\Exceptions\NotFoundException;
use Breezedoc\Exceptions\RateLimitException;
use Breezedoc\Exceptions\ValidationException;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;

class AbstractApiTest extends UnitTestCase
{
    private Configuration $config;
    private MockHttpClient $httpClient;
    private RequestBuilder $requestBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new Configuration('test-token');
        $this->httpClient = $this->createMockHttpClient();
        $factory = new Psr17Factory();
        $this->requestBuilder = new RequestBuilder($this->config, $factory, $factory);
    }

    private function createApi(): AbstractApi
    {
        return new class($this->httpClient, $this->requestBuilder) extends AbstractApi {
            public function testGet(string $path, array $query = []): array
            {
                return $this->get($path, $query);
            }

            public function testPost(string $path, array $body = []): array
            {
                return $this->post($path, $body);
            }

            public function testPut(string $path, array $body = []): array
            {
                return $this->put($path, $body);
            }

            public function testPatch(string $path, array $body = []): array
            {
                return $this->patch($path, $body);
            }

            public function testDelete(string $path): array
            {
                return $this->delete($path);
            }
        };
    }

    public function testGetRequestReturnsDecodedJson(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 1, 'name' => 'Test']));

        $api = $this->createApi();
        $result = $api->testGet('/test');

        $this->assertSame(['id' => 1, 'name' => 'Test'], $result);
    }

    public function testPostRequestReturnsDecodedJson(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 1, 'created' => true], 201));

        $api = $this->createApi();
        $result = $api->testPost('/test', ['name' => 'New Item']);

        $this->assertSame(['id' => 1, 'created' => true], $result);
    }

    public function testPutRequestReturnsDecodedJson(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 1, 'updated' => true]));

        $api = $this->createApi();
        $result = $api->testPut('/test/1', ['name' => 'Updated']);

        $this->assertSame(['id' => 1, 'updated' => true], $result);
    }

    public function testPatchRequestReturnsDecodedJson(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 1, 'patched' => true]));

        $api = $this->createApi();
        $result = $api->testPatch('/test/1', ['name' => 'Patched']);

        $this->assertSame(['id' => 1, 'patched' => true], $result);
    }

    public function testDeleteRequestReturnsEmptyArray(): void
    {
        $body = $this->psr17Factory->createStream('');
        $response = $this->psr17Factory->createResponse(204)->withBody($body);
        $this->httpClient->addResponse($response);

        $api = $this->createApi();
        $result = $api->testDelete('/test/1');

        $this->assertSame([], $result);
    }

    public function testThrowsAuthenticationExceptionOn401(): void
    {
        $this->httpClient->addResponse($this->createErrorResponse('Unauthenticated.', 401));

        $api = $this->createApi();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthenticated.');
        $api->testGet('/test');
    }

    public function testThrowsAuthorizationExceptionOn403(): void
    {
        $this->httpClient->addResponse($this->createErrorResponse('Forbidden.', 403));

        $api = $this->createApi();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Forbidden.');
        $api->testGet('/test');
    }

    public function testThrowsNotFoundExceptionOn404(): void
    {
        $this->httpClient->addResponse($this->createErrorResponse('Not found.', 404));

        $api = $this->createApi();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Not found.');
        $api->testGet('/test/999');
    }

    public function testThrowsValidationExceptionOn422(): void
    {
        $response = $this->createValidationErrorResponse('The given data was invalid.', [
            'title' => ['The title field is required.'],
            'email' => ['The email must be a valid email address.'],
        ]);
        $this->httpClient->addResponse($response);

        $api = $this->createApi();

        try {
            $api->testPost('/test', []);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('The given data was invalid.', $e->getMessage());
            $this->assertTrue($e->hasError('title'));
            $this->assertTrue($e->hasError('email'));
            $this->assertSame(['The title field is required.'], $e->getError('title'));
        }
    }

    public function testThrowsRateLimitExceptionOn429(): void
    {
        $body = $this->psr17Factory->createStream(json_encode(['message' => 'Too Many Attempts.']));
        $response = $this->psr17Factory->createResponse(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', '60')
            ->withBody($body);
        $this->httpClient->addResponse($response);

        $api = $this->createApi();

        try {
            $api->testGet('/test');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame('Too Many Attempts.', $e->getMessage());
            $this->assertSame(60, $e->getRetryAfter());
        }
    }

    public function testThrowsApiExceptionOnOtherErrors(): void
    {
        $this->httpClient->addResponse($this->createErrorResponse('Internal Server Error', 500));

        $api = $this->createApi();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $api->testGet('/test');
    }

    public function testGetWithQueryParams(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['data' => []]));

        $api = $this->createApi();
        $api->testGet('/test', ['page' => 2, 'per_page' => 10]);

        $requests = $this->httpClient->getRequests();
        $this->assertCount(1, $requests);
        $this->assertStringContainsString('page=2', (string) $requests[0]->getUri());
        $this->assertStringContainsString('per_page=10', (string) $requests[0]->getUri());
    }
}
