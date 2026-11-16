<?php

declare(strict_types=1);

namespace Breezedoc\V1\Models;

use DateTimeImmutable;

/**
 * The response of `GET /v1/documents/{id}/download`: a signed URL for the completed PDF,
 * which includes the signing audit certificate.
 *
 * BreezeDoc documents that the link expires after 15 minutes and that it must be treated
 * as a credential. Do not log it.
 */
class DownloadLink extends AbstractResource
{
    /**
     * Field names that are read first for the URL.
     */
    private const URL_KEYS = ['url', 'download_url', 'signed_url'];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $link = new self();
        $link->rawData = $data;

        return $link;
    }

    /**
     * The signed URL, or null when the response has none.
     *
     * BreezeDoc does not publish the field name. This reads `url`, `download_url` or
     * `signed_url`; else it takes the only top-level string value that starts with
     * "https://". When there are several such values and none of the known names, it
     * returns null.
     */
    public function getUrl(): ?string
    {
        foreach (self::URL_KEYS as $key) {
            $value = $this->rawData[$key] ?? null;
            if (is_string($value) && self::isHttpsUrl($value)) {
                return $value;
            }
        }

        $candidates = [];
        foreach ($this->rawData as $value) {
            if (is_string($value) && self::isHttpsUrl($value)) {
                $candidates[] = $value;
            }
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * The expiry time (`expires_at`), or null.
     */
    public function getExpiresAt(): ?DateTimeImmutable
    {
        return self::dateOrNull($this->rawData['expires_at'] ?? null);
    }

    private static function isHttpsUrl(string $value): bool
    {
        return strncasecmp($value, 'https://', 8) === 0;
    }
}
