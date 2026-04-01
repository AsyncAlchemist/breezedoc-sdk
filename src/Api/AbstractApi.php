<?php

declare(strict_types=1);

namespace Breezedoc\Api;

use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\AuthenticationException;
use Breezedoc\Exceptions\AuthorizationException;
use Breezedoc\Exceptions\NotFoundException;
use Breezedoc\Exceptions\RateLimitException;
use Breezedoc\Exceptions\ValidationException;
use Breezedoc\Http\RequestBuilder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for API resource classes.
 */
abstract class AbstractApi
{
    protected ClientInterface $httpClient;
    protected RequestBuilder $requestBuilder;

    public function __construct(ClientInterface $httpClient, RequestBuilder $requestBuilder)
    {
        $this->httpClient = $httpClient;
        $this->requestBuilder = $requestBuilder;
    }

    /**
     * Make a GET request.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    protected function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, null, $query);
    }

    /**
     * Make a POST request.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    protected function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * Make a PUT request.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    protected function put(string $path, array $body = []): array
    {
        return $this->request('PUT', $path, $body);
    }

    /**
     * Make a PATCH request.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    protected function patch(string $path, array $body = []): array
    {
        return $this->request('PATCH', $path, $body);
    }

    /**
     * Make a DELETE request.
     *
     * @return array<string, mixed>
     */
    protected function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Make an HTTP request.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     * @throws ApiException
     */
    protected function request(string $method, string $path, ?array $body = null, array $query = []): array
    {
        $request = $this->requestBuilder->build($method, $path, $body, $query);
        $response = $this->httpClient->sendRequest($request);

        return $this->handleResponse($response);
    }

    /**
     * Fetch a resource from an external URL (e.g. a pre-signed S3 URL).
     *
     * @throws \RuntimeException If the request fails
     */
    protected function fetchExternalUrl(string $url): string
    {
        $request = $this->requestBuilder->buildExternalRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return (string) $response->getBody();
        }

        throw new \RuntimeException(
            'Failed to fetch URL (HTTP ' . $statusCode . '): ' . $url
        );
    }

    /**
     * Handle the HTTP response.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    protected function handleResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = $this->decodeJson($body);

        if ($statusCode >= 200 && $statusCode < 300) {
            return $data;
        }

        $message = $data['message'] ?? 'Unknown error';
        $this->throwException($statusCode, $message, $data, $response);
    }

    /**
     * Decode JSON response body.
     *
     * @return array<string, mixed>
     */
    protected function decodeJson(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $data;
    }

    /**
     * Throw the appropriate exception based on status code.
     *
     * @param array<string, mixed> $data
     * @throws ApiException
     * @return never
     */
    protected function throwException(
        int $statusCode,
        string $message,
        array $data,
        ResponseInterface $response
    ): void {
        switch ($statusCode) {
            case 401:
                throw new AuthenticationException($message);

            case 403:
                throw new AuthorizationException($message);

            case 404:
                throw new NotFoundException($message);

            case 422:
                $errors = $data['errors'] ?? [];
                throw new ValidationException($message, $errors);

            case 429:
                $retryAfter = $response->hasHeader('Retry-After')
                    ? (int) $response->getHeaderLine('Retry-After')
                    : null;
                throw new RateLimitException($message, $retryAfter);

            default:
                throw new ApiException($message, $statusCode, null, $data);
        }
    }
}
