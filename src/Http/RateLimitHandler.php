<?php

declare(strict_types=1);

namespace Breezedoc\Http;

use Breezedoc\Exceptions\RateLimitException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP client wrapper that handles rate limiting with automatic retry.
 *
 * When a 429 response is received, this handler will wait and retry
 * the request up to the configured maximum number of retries.
 */
class RateLimitHandler implements ClientInterface
{
    private ClientInterface $httpClient;
    private int $maxRetries;

    public function __construct(ClientInterface $httpClient, int $maxRetries = 3)
    {
        $this->httpClient = $httpClient;
        $this->maxRetries = $maxRetries;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $attempts = 0;

        while (true) {
            $response = $this->httpClient->sendRequest($request);

            if ($response->getStatusCode() !== 429) {
                return $response;
            }

            $attempts++;

            if ($attempts > $this->maxRetries) {
                $body = json_decode((string) $response->getBody(), true);
                $message = $body['message'] ?? 'Rate Limit Exceeded';
                $retryAfter = $this->getRetryAfter($response);

                throw new RateLimitException($message, $retryAfter);
            }

            $this->wait($response, $attempts);
        }
    }

    /**
     * Wait before retrying.
     */
    private function wait(ResponseInterface $response, int $attempt): void
    {
        $retryAfter = $this->getRetryAfter($response);

        if ($retryAfter !== null) {
            $seconds = $retryAfter;
        } else {
            // Exponential backoff: 1s, 2s, 4s, etc.
            $seconds = (int) pow(2, $attempt - 1);
        }

        // Cap at 60 seconds
        $seconds = min($seconds, 60);

        sleep($seconds);
    }

    /**
     * Get the Retry-After header value in seconds.
     */
    private function getRetryAfter(ResponseInterface $response): ?int
    {
        if (!$response->hasHeader('Retry-After')) {
            return null;
        }

        $retryAfter = $response->getHeaderLine('Retry-After');

        // Check if it's a number of seconds
        if (is_numeric($retryAfter)) {
            return (int) $retryAfter;
        }

        // It might be a date string
        $timestamp = strtotime($retryAfter);
        if ($timestamp !== false) {
            return max(0, $timestamp - time());
        }

        return null;
    }
}
