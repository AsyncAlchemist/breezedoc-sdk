<?php

declare(strict_types=1);

namespace Breezedoc;

use Breezedoc\Config\Configuration;
use Composer\InstalledVersions;
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

    /**
     * Get the installed SDK version.
     *
     * Reads from Composer's runtime version data so the version stays in sync
     * with the installed package without a hand-maintained constant. Returns
     * `"dev"` if the SDK is not running from a Composer-installed package
     * (e.g. when running the SDK's own test suite from a checkout).
     */
    public static function getVersion(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('asyncalchemist/breezedoc-sdk') ?? 'dev';
        } catch (\OutOfBoundsException $e) {
            return 'dev';
        }
    }
}
