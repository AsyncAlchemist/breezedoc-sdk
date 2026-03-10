<?php

declare(strict_types=1);

namespace Breezedoc\Models;

use DateTimeImmutable;

/**
 * Represents a Breezedoc template.
 */
class Template extends AbstractModel
{
    private int $id;
    private string $title;
    private string $slug;
    private ?DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $template = new self();
        $template->rawData = $data;

        $template->id = (int) $data['id'];
        $template->title = (string) $data['title'];
        $template->slug = (string) $data['slug'];
        $template->createdAt = self::parseDateTime($data['created_at'] ?? null);
        $template->updatedAt = self::parseDateTime($data['updated_at'] ?? null);

        return $template;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
