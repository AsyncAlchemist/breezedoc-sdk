<?php

declare(strict_types=1);

namespace Breezedoc\Config;

use InvalidArgumentException;

/**
 * SDK configuration settings.
 */
class Configuration
{
    private string $token;
    private string $baseUrl = 'https://breezedoc.com/api';
    private int $timeout = 30;
    private int $maxRetries = 3;

    public function __construct(string $token)
    {
        if ($token === '') {
            throw new InvalidArgumentException('API token cannot be empty');
        }

        $this->token = $token;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return $this
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @return $this
     */
    public function setTimeout(int $timeout): self
    {
        if ($timeout <= 0) {
            throw new InvalidArgumentException('Timeout must be a positive integer');
        }

        $this->timeout = $timeout;
        return $this;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * @return $this
     */
    public function setMaxRetries(int $maxRetries): self
    {
        if ($maxRetries < 0) {
            throw new InvalidArgumentException('Max retries cannot be negative');
        }

        $this->maxRetries = $maxRetries;
        return $this;
    }
}
