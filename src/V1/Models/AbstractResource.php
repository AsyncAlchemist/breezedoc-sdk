<?php

declare(strict_types=1);

namespace Breezedoc\V1\Models;

use DateTimeImmutable;

/**
 * Base class for Team API v1 resources.
 *
 * BreezeDoc's published OpenAPI spec gives no response schemas for the Team API v1; it
 * states only that responses are "JSON metadata only", that IDs are strings with the
 * prefixes `doc_`, `tpl_`, `rcp_` and `evt_`, and that timestamps are ISO 8601 UTC. The
 * typed getters therefore read their fields leniently and return null (or an empty array)
 * when a field is missing or has an unexpected type. {@see toArray()} and {@see get()}
 * always give the full response.
 */
abstract class AbstractResource
{
    /**
     * @var array<string, mixed>
     */
    protected array $rawData = [];

    /**
     * Get the raw response data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rawData;
    }

    /**
     * Get one raw field.
     *
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return array_key_exists($key, $this->rawData) ? $this->rawData[$key] : $default;
    }

    /**
     * @param mixed $value
     */
    protected static function stringOrNull($value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    protected static function intOrNull($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?[0-9]+\z/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    protected static function dateOrNull($value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * The `id` field as a string ('' when missing).
     *
     * @param array<string, mixed> $data
     */
    protected static function idOf(array $data): string
    {
        return self::stringOrNull($data['id'] ?? null) ?? '';
    }

    /**
     * Whether an array is a list (keys 0..n-1), as array_is_list() in PHP 8.1.
     *
     * @param array<mixed> $value
     */
    public static function isList(array $value): bool
    {
        $expected = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }
}
