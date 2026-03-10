<?php

declare(strict_types=1);

namespace Breezedoc\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Factory for creating PSR-17 and PSR-18 HTTP components.
 *
 * Uses php-http/discovery to auto-discover available implementations,
 * or accepts user-provided implementations.
 */
class HttpClientFactory
{
    /**
     * Create or return a PSR-18 HTTP client.
     */
    public static function createHttpClient(?ClientInterface $client = null): ClientInterface
    {
        if ($client !== null) {
            return $client;
        }

        return Psr18ClientDiscovery::find();
    }

    /**
     * Create a PSR-17 request factory.
     */
    public static function createRequestFactory(): RequestFactoryInterface
    {
        return Psr17FactoryDiscovery::findRequestFactory();
    }

    /**
     * Create a PSR-17 stream factory.
     */
    public static function createStreamFactory(): StreamFactoryInterface
    {
        return Psr17FactoryDiscovery::findStreamFactory();
    }
}
