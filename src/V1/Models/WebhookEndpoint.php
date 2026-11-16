<?php

declare(strict_types=1);

namespace Breezedoc\V1\Models;

use DateTimeImmutable;

/**
 * A webhook endpoint of the team.
 *
 * The signing secret (`whsec_...`) is in the response of {@see \Breezedoc\V1\Api\Webhooks::create()}
 * and {@see \Breezedoc\V1\Api\Webhooks::rotateSecret()} only. BreezeDoc shows it once: store it.
 */
class WebhookEndpoint extends AbstractResource
{
    /**
     * The documented prefix of a webhook signing secret.
     */
    public const SECRET_PREFIX = 'whsec_';

    private string $id;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $endpoint = new self();
        $endpoint->rawData = $data;
        $endpoint->id = self::idOf($data);

        return $endpoint;
    }

    /**
     * The endpoint ID ('' when the response has none).
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * The destination URL, or null.
     */
    public function getUrl(): ?string
    {
        return self::stringOrNull($this->rawData['url'] ?? null);
    }

    /**
     * The subscribed event types.
     *
     * @return array<string>
     */
    public function getEvents(): array
    {
        $events = $this->rawData['events'] ?? null;
        if (!is_array($events)) {
            return [];
        }

        return array_values(array_filter($events, 'is_string'));
    }

    /**
     * The signing secret, or null when the response has none.
     *
     * BreezeDoc does not publish the field name. This reads `secret` or `signing_secret`;
     * else it takes the first top-level string value that starts with "whsec_".
     */
    public function getSecret(): ?string
    {
        foreach (['secret', 'signing_secret'] as $key) {
            $value = $this->rawData[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        foreach ($this->rawData as $value) {
            if (is_string($value) && strncmp($value, self::SECRET_PREFIX, strlen(self::SECRET_PREFIX)) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function getStatus(): ?string
    {
        return self::stringOrNull($this->rawData['status'] ?? null);
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return self::dateOrNull($this->rawData['created_at'] ?? null);
    }
}
