<?php

declare(strict_types=1);

namespace Breezedoc\Http;

use Breezedoc\Config\Configuration;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds PSR-7 HTTP requests for the Breezedoc API.
 */
class RequestBuilder
{
    private Configuration $config;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        Configuration $config,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->config = $config;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Build a PSR-7 request.
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $path API endpoint path
     * @param array<string, mixed>|null $body Request body (will be JSON encoded)
     * @param array<string, mixed> $query Query parameters
     */
    public function build(string $method, string $path, ?array $body = null, array $query = []): RequestInterface
    {
        $url = $this->buildUrl($path, $query);

        $request = $this->requestFactory->createRequest($method, $url);
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->config->getToken());
        $request = $request->withHeader('Accept', 'application/json');

        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $encoded = json_encode($body);
            if ($encoded === false) {
                throw new \InvalidArgumentException('Failed to encode request body as JSON: ' . json_last_error_msg());
            }
            $stream = $this->streamFactory->createStream($encoded);
            $request = $request->withBody($stream);
        }

        return $request;
    }

    /**
     * Build the full URL for an API endpoint.
     *
     * @param array<string, mixed> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $baseUrl = rtrim($this->config->getBaseUrl(), '/');
        $path = '/' . ltrim($path, '/');
        $url = $baseUrl . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}
