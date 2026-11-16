<?php

declare(strict_types=1);

namespace Breezedoc\Http;

use Breezedoc\Breezedoc;
use Breezedoc\Config\Configuration;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Builds PSR-7 HTTP requests for the Breezedoc API.
 *
 * A builder made with {@see forTeamApi()} targets the Team API v1: it uses the team base URL
 * and the team API key of the configuration instead of the base URL and the token.
 */
class RequestBuilder
{
    private Configuration $config;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private bool $teamApi = false;

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
     * Create a builder for the Team API v1 (team base URL and team API key).
     */
    public static function forTeamApi(
        Configuration $config,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ): self {
        $builder = new self($config, $requestFactory, $streamFactory);
        $builder->teamApi = true;

        return $builder;
    }

    /**
     * Whether this builder targets the Team API v1.
     */
    public function isTeamApi(): bool
    {
        return $this->teamApi;
    }

    /**
     * Create a PSR-7 stream with the given content (for example a multipart body).
     */
    public function createStream(string $content): StreamInterface
    {
        return $this->streamFactory->createStream($content);
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
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->credential());
        $request = $request->withHeader('Accept', 'application/json');
        $request = $request->withHeader(
            'User-Agent',
            'asyncalchemist/breezedoc-sdk/' . Breezedoc::getVersion()
                . ' (+https://github.com/AsyncAlchemist/breezedoc-sdk)'
        );

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
     * Build a PSR-7 request for an external URL (no API base URL or auth).
     *
     * Used for fetching resources like pre-signed S3 URLs returned by the API.
     */
    public function buildExternalRequest(string $method, string $url): RequestInterface
    {
        return $this->requestFactory->createRequest($method, $url);
    }

    /**
     * The bearer credential: the team API key for the Team API v1, else the token.
     */
    private function credential(): string
    {
        if (!$this->teamApi) {
            return $this->config->getToken();
        }

        $key = $this->config->getTeamApiKey();
        if ($key === null) {
            throw new \InvalidArgumentException(
                'A team API key is required for the Team API v1. Call Configuration::setTeamApiKey($key).'
            );
        }

        return $key;
    }

    /**
     * Build the full URL for an API endpoint.
     *
     * @param array<string, mixed> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $base = $this->teamApi ? $this->config->getTeamBaseUrl() : $this->config->getBaseUrl();
        $baseUrl = rtrim($base, '/');
        $path = '/' . ltrim($path, '/');
        $url = $baseUrl . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}
