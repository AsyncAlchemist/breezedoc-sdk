<?php

declare(strict_types=1);

namespace Breezedoc\Web;

/**
 * Immutable snapshot of an authenticated web session.
 *
 * Holds the cookies for a logged-in breezedoc.com session (a simple name => value
 * map), the account email the session belongs to, and the login timestamp used for
 * TTL checks. The password is never stored here.
 */
class SessionState
{
    private string $email;
    private int $createdAt;

    /**
     * @var array<string, string>
     */
    private array $cookies;

    /**
     * @param array<string, string> $cookies Cookie name => value pairs
     */
    public function __construct(string $email, int $createdAt, array $cookies)
    {
        $this->email = $email;
        $this->createdAt = $createdAt;
        $this->cookies = $cookies;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, string>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Whether this session is older than the given TTL (in seconds).
     */
    public function isExpired(int $ttl, int $now): bool
    {
        return ($now - $this->createdAt) >= $ttl;
    }

    /**
     * @return array{email: string, created_at: int, cookies: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'created_at' => $this->createdAt,
            'cookies' => $this->cookies,
        ];
    }

    /**
     * Rehydrate from a persisted array, or null if the data is not a valid session.
     *
     * Returns null for anything that is not a well-formed name => value cookie map,
     * which also causes sessions written by an older format to be discarded (and a
     * fresh login performed) rather than misused.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        if (!isset($data['email'], $data['created_at'], $data['cookies'])) {
            return null;
        }

        if (!is_string($data['email']) || !is_array($data['cookies'])) {
            return null;
        }

        $cookies = [];
        foreach ($data['cookies'] as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                return null;
            }
            $cookies[$name] = $value;
        }

        return new self($data['email'], (int) $data['created_at'], $cookies);
    }
}
