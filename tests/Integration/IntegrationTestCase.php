<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Base test case for integration tests.
 *
 * Integration tests make live API requests to the Breezedoc API.
 * They require a valid API token to be set in the environment.
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * @var string|null
     */
    protected ?string $apiToken = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiToken = $this->getApiToken();

        if ($this->apiToken === null || $this->apiToken === '') {
            $this->markTestSkipped(
                'Integration tests require BREEZEDOC_API_TOKEN environment variable to be set.'
            );
        }
    }

    /**
     * Get the API token from the environment.
     *
     * Checks for both BREEZEDOC_API_TOKEN and BREEZEDOC_PAT environment variables.
     */
    protected function getApiToken(): ?string
    {
        $envVars = ['BREEZEDOC_API_TOKEN', 'BREEZEDOC_PAT'];

        foreach ($envVars as $varName) {
            // Check environment variable
            $token = getenv($varName);
            if ($token !== false && $token !== '') {
                return $token;
            }

            // Check $_ENV (populated by phpdotenv)
            if (isset($_ENV[$varName]) && $_ENV[$varName] !== '') {
                return $_ENV[$varName];
            }

            // Check $_SERVER (sometimes used by phpunit)
            if (isset($_SERVER[$varName]) && $_SERVER[$varName] !== '') {
                return $_SERVER[$varName];
            }
        }

        return null;
    }

    /**
     * Get the test email address for integration tests.
     *
     * This should be an email address you control to avoid sending
     * test emails to unknown recipients.
     */
    protected function getTestEmail(): string
    {
        $envVars = ['BREEZEDOC_TEST_EMAIL'];

        foreach ($envVars as $varName) {
            $value = getenv($varName);
            if ($value !== false && $value !== '') {
                return $value;
            }

            if (isset($_ENV[$varName]) && $_ENV[$varName] !== '') {
                return $_ENV[$varName];
            }

            if (isset($_SERVER[$varName]) && $_SERVER[$varName] !== '') {
                return $_SERVER[$varName];
            }
        }

        $this->markTestSkipped(
            'Integration tests require BREEZEDOC_TEST_EMAIL environment variable to be set.'
        );
    }
}
