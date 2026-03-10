<?php

declare(strict_types=1);

namespace Breezedoc;

use Breezedoc\Config\Configuration;
use Psr\Http\Client\ClientInterface;

/**
 * Static factory for creating Breezedoc clients.
 *
 * Example usage:
 *
 *     // Simple usage with just a token
 *     $client = Breezedoc::client('your-api-token');
 *
 *     // With custom configuration
 *     $config = new Configuration('your-api-token');
 *     $config->setTimeout(60)->setMaxRetries(5);
 *     $client = Breezedoc::client($config);
 *
 *     // Bring your own HTTP client
 *     $client = Breezedoc::client('your-api-token', $guzzleClient);
 */
class Breezedoc
{
    /**
     * Create a new Breezedoc client.
     *
     * @param string|Configuration $config API token or Configuration instance
     * @param ClientInterface|null $httpClient Optional PSR-18 HTTP client
     */
    public static function client($config, ?ClientInterface $httpClient = null): Client
    {
        return new Client($config, $httpClient);
    }
}
