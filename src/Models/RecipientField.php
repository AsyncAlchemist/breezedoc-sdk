<?php

declare(strict_types=1);

namespace Breezedoc\Models;

/**
 * Represents a field value submitted by a recipient.
 *
 * The submitted value is stored in the properties array, with the key
 * depending on the field type:
 *   - Text/Email: "text"
 *   - Signature/Initials: "image" (relative path), "text", "fontFamily"
 *   - Date: "date"
 *   - Checkbox: "checked"
 *   - Dropdown: "text"
 */
class RecipientField extends AbstractModel
{
    private int $fieldId;

    /**
     * @var array<string, mixed>
     */
    private array $properties;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $field = new self();
        $field->rawData = $data;

        $field->fieldId = (int) $data['field_id'];
        $field->properties = $data['properties'] ?? [];

        return $field;
    }

    public function getFieldId(): int
    {
        return $this->fieldId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Get the text value (for text, email, dropdown, and signature fields).
     */
    public function getText(): ?string
    {
        return isset($this->properties['text']) ? (string) $this->properties['text'] : null;
    }

    /**
     * Get the date value (for date fields).
     */
    public function getDate(): ?string
    {
        return isset($this->properties['date']) ? (string) $this->properties['date'] : null;
    }

    /**
     * Get whether the checkbox is checked (for checkbox fields).
     */
    public function isChecked(): bool
    {
        return !empty($this->properties['checked']);
    }

    /**
     * Get the signature/initials image path (for signature/initials fields).
     */
    public function getImage(): ?string
    {
        return isset($this->properties['image']) ? (string) $this->properties['image'] : null;
    }

    /**
     * Get the font family used for a typed signature.
     */
    public function getFontFamily(): ?string
    {
        return isset($this->properties['fontFamily']) ? (string) $this->properties['fontFamily'] : null;
    }

    /**
     * Whether the field value has been committed by the recipient.
     */
    public function isCommitted(): bool
    {
        return !empty($this->properties['committed']);
    }

    /**
     * Get the submitted value as a normalized string, regardless of field type.
     *
     * Returns the most meaningful string representation:
     *   - Text/Email/Dropdown: the text value
     *   - Date: the date string
     *   - Checkbox: "true" or "false"
     *   - Signature/Initials: the text (typed name) or null
     */
    public function getValue(): ?string
    {
        if (isset($this->properties['text'])) {
            return (string) $this->properties['text'];
        }

        if (isset($this->properties['date'])) {
            return (string) $this->properties['date'];
        }

        if (isset($this->properties['checked'])) {
            return $this->properties['checked'] ? 'true' : 'false';
        }

        return null;
    }
}
