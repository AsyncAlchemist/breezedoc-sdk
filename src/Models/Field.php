<?php

declare(strict_types=1);

namespace Breezedoc\Models;

use Breezedoc\Config\FieldType;

/**
 * Represents a field on a document (signature, text, checkbox, etc.).
 */
class Field extends AbstractModel
{
    private int $id;
    private int $documentFileId;
    private int $page;
    private int $party;
    private string $fieldTypeId;
    private string $name;
    private ?int $recipientId;
    private FieldProperties $properties;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $field = new self();
        $field->rawData = $data;

        $field->id = (int) $data['id'];
        $field->documentFileId = (int) $data['document_file_id'];
        $field->page = (int) $data['page'];
        $field->party = (int) $data['party'];
        $field->fieldTypeId = (string) $data['field_type_id'];
        $field->name = (string) $data['name'];
        $field->recipientId = isset($data['recipient_id']) ? (int) $data['recipient_id'] : null;
        $field->properties = FieldProperties::fromArray($data['properties'] ?? []);

        return $field;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDocumentFileId(): int
    {
        return $this->documentFileId;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getParty(): int
    {
        return $this->party;
    }

    public function getFieldTypeId(): string
    {
        return $this->fieldTypeId;
    }

    /**
     * Get the human-readable field type name.
     */
    public function getFieldTypeName(): ?string
    {
        return FieldType::getName($this->fieldTypeId);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRecipientId(): ?int
    {
        return $this->recipientId;
    }

    public function getProperties(): FieldProperties
    {
        return $this->properties;
    }

    public function isSignature(): bool
    {
        return $this->fieldTypeId === FieldType::SIGNATURE;
    }

    public function isInitials(): bool
    {
        return $this->fieldTypeId === FieldType::INITIALS;
    }

    public function isRequired(): bool
    {
        return $this->properties->isRequired();
    }
}
