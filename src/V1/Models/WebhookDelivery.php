<?php

declare(strict_types=1);

namespace Breezedoc\V1\Models;

use DateTimeImmutable;

/**
 * One delivery log entry of a webhook endpoint (kept for 30 days).
 *
 * BreezeDoc documents that a log keeps the status, the latency and the first 1 KB of the
 * response, but not the field names. Use {@see toArray()} or {@see get()} for them.
 */
class WebhookDelivery extends AbstractResource
{
    private string $id;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $delivery = new self();
        $delivery->rawData = $data;
        $delivery->id = self::idOf($data);

        return $delivery;
    }

    /**
     * The delivery ID, for {@see \Breezedoc\V1\Api\Webhooks::redeliver()} ('' when the response has none).
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return self::dateOrNull($this->rawData['created_at'] ?? null);
    }
}
