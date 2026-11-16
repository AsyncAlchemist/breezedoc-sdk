<?php

declare(strict_types=1);

namespace Breezedoc\Config;

use Breezedoc\Web\FileSessionStore;
use Breezedoc\Web\SessionStore;
use InvalidArgumentException;

/**
 * SDK configuration settings.
 *
 * BreezeDoc has two REST APIs with two kinds of credential:
 *
 * - The original API at `https://breezedoc.com/api` takes a personal access token (or an
 *   OAuth access token). This is the `$token` of the constructor.
 * - The Team API v1 at `https://breezedoc.com/api/v1` takes a team API key (`bd_live_...`).
 *   Set it with {@see setTeamApiKey()}, or create the configuration with
 *   {@see forTeamApiKey()}.
 *
 * BreezeDoc documents that team keys work only with `/api/v1`, and personal access tokens
 * only with `/api`.
 */
class Configuration
{
    private string $token;
    private string $baseUrl = 'https://breezedoc.com/api';
    private ?string $teamApiKey = null;
    private string $teamBaseUrl = 'https://breezedoc.com/api/v1';
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

    /**
     * Create a configuration for the Team API v1 only.
     *
     * The key is also stored as the constructor token, because the constructor requires one.
     * Calls to the original API (`/api`) with a team key answer 401.
     */
    public static function forTeamApiKey(string $teamApiKey): self
    {
        $config = new self($teamApiKey);
        $config->setTeamApiKey($teamApiKey);

        return $config;
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

    /**
     * Set the team API key (`bd_live_...`) for the Team API v1.
     *
     * @return $this
     */
    public function setTeamApiKey(string $teamApiKey): self
    {
        if ($teamApiKey === '') {
            throw new InvalidArgumentException('Team API key cannot be empty');
        }

        $this->teamApiKey = $teamApiKey;
        return $this;
    }

    public function getTeamApiKey(): ?string
    {
        return $this->teamApiKey;
    }

    public function hasTeamApiKey(): bool
    {
        return $this->teamApiKey !== null;
    }

    public function getTeamBaseUrl(): string
    {
        return $this->teamBaseUrl;
    }

    /**
     * @return $this
     */
    public function setTeamBaseUrl(string $teamBaseUrl): self
    {
        $this->teamBaseUrl = $teamBaseUrl;
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
     * These are only needed for the website-based features: the signed-PDF
     * download ({@see \Breezedoc\Api\Documents::downloadPdf()}) and the document
     * delete ({@see \Breezedoc\Api\Documents::deleteDocument()}). They are the
     * website email and password, not the API token.
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
