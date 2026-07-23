<?php

declare(strict_types=1);

namespace Breezedoc\Web;

use GuzzleHttp\Client as GuzzleClient;

/**
 * Builds the Guzzle client used for the website login/download flow.
 *
 * Kept separate from the core PSR-18 API transport so the web flow can be wired up
 * (and swapped for a mock in tests) independently.
 */
class WebClientFactory
{
    public static function create(int $timeout): GuzzleClient
    {
        return new GuzzleClient([
            'timeout' => $timeout,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);
    }
}
