<?php

declare(strict_types=1);

namespace Breezedoc\Models;

/**
 * Represents a field value submitted by a recipient.
 */
class RecipientField extends AbstractModel
{
    private int $id;
    private ?string $value;
    private ?string $imageUrl;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $field = new self();
        $field->rawData = $data;

        $field->id = (int) $data['id'];
        $field->value = $data['value'] ?? null;
        $field->imageUrl = $data['image_url'] ?? null;

        return $field;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }
}
