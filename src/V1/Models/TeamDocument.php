<?php

declare(strict_types=1);

namespace Breezedoc\V1\Models;

use DateTimeImmutable;

/**
 * A team document (`doc_...`) of the Team API v1.
 *
 * Responses contain metadata only (never the document text or field values), plus
 * `review_url`, a link to review the draft in BreezeDoc (null unless the document is unsent).
 */
class TeamDocument extends AbstractResource
{
    private string $id;

    /**
     * @var array<TeamRecipient>
     */
    private array $recipients = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $document = new self();
        $document->rawData = $data;
        $document->id = self::idOf($data);

        $recipients = $data['recipients'] ?? null;
        if (is_array($recipients)) {
            foreach ($recipients as $recipient) {
                if (is_array($recipient)) {
                    $document->recipients[] = TeamRecipient::fromArray($recipient);
                }
            }
        }

        return $document;
    }

    /**
     * The document ID, for example "doc_456".
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return self::stringOrNull($this->rawData['title'] ?? null);
    }

    /**
     * The status: one of the {@see DocumentStatus} values, or null.
     */
    public function getStatus(): ?string
    {
        return self::stringOrNull($this->rawData['status'] ?? null);
    }

    public function isDraft(): bool
    {
        return $this->getStatus() === DocumentStatus::DRAFT;
    }

    public function isSent(): bool
    {
        return $this->getStatus() === DocumentStatus::SENT;
    }

    public function isCompleted(): bool
    {
        return $this->getStatus() === DocumentStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->getStatus() === DocumentStatus::CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->getStatus() === DocumentStatus::EXPIRED;
    }

    /**
     * The link to review an unsent draft in BreezeDoc, or null.
     */
    public function getReviewUrl(): ?string
    {
        return self::stringOrNull($this->rawData['review_url'] ?? null);
    }

    /**
     * @return array<TeamRecipient>
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return self::dateOrNull($this->rawData['created_at'] ?? null);
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return self::dateOrNull($this->rawData['completed_at'] ?? null);
    }
}
