<?php

declare(strict_types=1);

namespace Breezedoc\Config;

use Breezedoc\Web\FileSessionStore;
use Breezedoc\Web\SessionStore;
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

    private ?string $webEmail = null;
    private ?string $webPassword = null;
    private string $webBaseUrl = 'https://breezedoc.com';
    private int $webSessionTtl = 3600;
    private ?SessionStore $sessionStore = null;

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

    /**
     * Set the breezedoc.com website login credentials.
     *
     * These are only needed for the login-based PDF download feature
     * ({@see \Breezedoc\Api\Documents::downloadPdf()}). They are the website
     * email and password, not the API token.
     *
     * @return $this
     */
    public function setWebLogin(string $email, string $password): self
    {
        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('Web login email and password cannot be empty');
        }

        $this->webEmail = $email;
        $this->webPassword = $password;
        return $this;
    }

    public function hasWebLogin(): bool
    {
        return $this->webEmail !== null && $this->webPassword !== null;
    }

    public function getWebEmail(): ?string
    {
        return $this->webEmail;
    }

    public function getWebPassword(): ?string
    {
        return $this->webPassword;
    }

    public function getWebBaseUrl(): string
    {
        return $this->webBaseUrl;
    }

    /**
     * @return $this
     */
    public function setWebBaseUrl(string $webBaseUrl): self
    {
        $this->webBaseUrl = $webBaseUrl;
        return $this;
    }

    public function getWebSessionTtl(): int
    {
        return $this->webSessionTtl;
    }

    /**
     * Set how long (in seconds) a cached web session is trusted before a proactive
     * re-login. An expired session is still detected and refreshed reactively, so
     * this only avoids a wasted round-trip.
     *
     * @return $this
     */
    public function setWebSessionTtl(int $webSessionTtl): self
    {
        if ($webSessionTtl <= 0) {
            throw new InvalidArgumentException('Web session TTL must be a positive integer');
        }

        $this->webSessionTtl = $webSessionTtl;
        return $this;
    }

    /**
     * @return $this
     */
    public function setSessionStore(SessionStore $sessionStore): self
    {
        $this->sessionStore = $sessionStore;
        return $this;
    }

    /**
     * Get the session store, defaulting to a file store at ~/.breezedoc/session.json.
     */
    public function getSessionStore(): SessionStore
    {
        if ($this->sessionStore === null) {
            $this->sessionStore = new FileSessionStore($this->defaultSessionPath());
        }

        return $this->sessionStore;
    }

    private function defaultSessionPath(): string
    {
        $home = getenv('HOME');
        if ($home === false || $home === '') {
            $home = sys_get_temp_dir();
        }

        return rtrim($home, '/') . '/.breezedoc/session.json';
    }
}
