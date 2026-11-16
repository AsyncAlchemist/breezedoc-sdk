<?php

declare(strict_types=1);

namespace Breezedoc\V1\Models;

use DateTimeImmutable;

/**
 * One audit event of a document (`GET /v1/documents/{id}/events`).
 */
class DocumentEvent extends AbstractResource
{
    private string $id;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $event = new self();
        $event->rawData = $data;
        $event->id = self::idOf($data);

        return $event;
    }

    /**
     * The event ID ('' when the response has none).
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return self::stringOrNull($this->rawData['type'] ?? null);
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return self::dateOrNull($this->rawData['created_at'] ?? null);
    }
}
