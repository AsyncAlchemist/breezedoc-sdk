<?php

declare(strict_types=1);

namespace Breezedoc\Web;

/**
 * Immutable snapshot of an authenticated web session.
 *
 * Holds the cookies for a logged-in breezedoc.com session (as produced by
 * {@see \GuzzleHttp\Cookie\CookieJar::toArray()}), the account email the session
 * belongs to, and the login timestamp used for TTL checks. The password is never
 * stored here.
 */
class SessionState
{
    private string $email;
    private int $createdAt;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $cookies;

    /**
     * @param array<int, array<string, mixed>> $cookies Cookie jar contents (CookieJar::toArray())
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
     * @return array<int, array<string, mixed>>
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
     * @return array{email: string, created_at: int, cookies: array<int, array<string, mixed>>}
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

        /** @var array<int, array<string, mixed>> $cookies */
        $cookies = array_values($data['cookies']);

        return new self($data['email'], (int) $data['created_at'], $cookies);
    }
}
