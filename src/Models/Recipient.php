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
    private string $slug;
    private string $name;
    private string $email;
    private int $party;
    private bool $owner;
    private ?DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;
    private ?DateTimeImmutable $completedAt;

    /**
     * @var array<RecipientField>
     */
    private array $fields = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $recipient = new self();
        $recipient->rawData = $data;

        $recipient->id = (int) $data['id'];
        $recipient->slug = (string) $data['slug'];
        $recipient->name = (string) $data['name'];
        $recipient->email = (string) $data['email'];
        $recipient->party = (int) $data['party'];
        $recipient->owner = (bool) ($data['owner'] ?? false);
        $recipient->createdAt = self::parseDateTime($data['created_at'] ?? null);
        $recipient->updatedAt = self::parseDateTime($data['updated_at'] ?? null);
        $recipient->completedAt = self::parseDateTime($data['completed_at'] ?? null);

        if (isset($data['fields']) && is_array($data['fields'])) {
            foreach ($data['fields'] as $fieldData) {
                $recipient->fields[] = RecipientField::fromArray($fieldData);
            }
        }

        return $recipient;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
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

    /**
     * @return array<RecipientField>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
