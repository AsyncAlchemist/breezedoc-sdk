<?php

declare(strict_types=1);

namespace Breezedoc\Models;

use DateTimeImmutable;

/**
 * Represents a document recipient (signer).
 */
class Recipient extends AbstractModel
{
    private int $id;
    private ?string $slug;
    private string $name;
    private string $email;
    private int $party;
    private bool $owner;
    private ?DateTimeImmutable $sentAt;
    private ?DateTimeImmutable $openedAt;
    private ?DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;
    private ?DateTimeImmutable $completedAt;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $recipient = new self();
        $recipient->rawData = $data;

        $recipient->id = (int) $data['id'];
        $recipient->slug = isset($data['slug']) ? (string) $data['slug'] : null;
        $recipient->name = (string) $data['name'];
        $recipient->email = (string) $data['email'];
        $recipient->party = (int) $data['party'];
        $recipient->owner = (bool) ($data['owner'] ?? false);
        $recipient->sentAt = self::parseDateTime($data['sent_at'] ?? null);
        $recipient->openedAt = self::parseDateTime($data['opened_at'] ?? null);
        $recipient->createdAt = self::parseDateTime($data['created_at'] ?? null);
        $recipient->updatedAt = self::parseDateTime($data['updated_at'] ?? null);
        $recipient->completedAt = self::parseDateTime($data['completed_at'] ?? null);

        return $recipient;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getParty(): int
    {
        return $this->party;
    }

    public function isOwner(): bool
    {
        return $this->owner;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function isSent(): bool
    {
        return $this->sentAt !== null;
    }

    public function getOpenedAt(): ?DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function isOpened(): bool
    {
        return $this->openedAt !== null;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }
}
