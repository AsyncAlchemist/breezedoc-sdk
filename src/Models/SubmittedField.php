<?php

declare(strict_types=1);

namespace Breezedoc\Models;

/**
 * Represents a field that has been submitted by a recipient.
 *
 * Combines a Field (definition + submitted data) with the Recipient
 * who submitted it, providing convenient access to both the field
 * metadata and the submitted value.
 */
class SubmittedField
{
    /** @var Field */
    private $field;

    /** @var Recipient */
    private $recipient;

    private function __construct()
    {
    }

    public static function create(Field $field, Recipient $recipient): self
    {
        $instance = new self();
        $instance->field = $field;
        $instance->recipient = $recipient;

        return $instance;
    }

    public function getId(): int
    {
        return $this->field->getId();
    }

    public function getName(): string
    {
        return $this->field->getName();
    }

    /**
     * Get the field label (display name shown to signers).
     */
    public function getLabel(): ?string
    {
        return $this->field->getProperties()->getLabel();
    }

    public function getFieldTypeId(): string
    {
        return $this->field->getFieldTypeId();
    }

    public function getFieldTypeName(): ?string
    {
        return $this->field->getFieldTypeName();
    }

    /**
     * Get the submitted value as a normalized string.
     *
     * Returns the most meaningful string representation regardless of field type:
     *   - Text/Email/Dropdown: the entered text
     *   - Date: the date string (e.g., "03-31-2026")
     *   - Checkbox: "true" or "false"
     *   - Signature/Initials: the typed name, or null if drawn
     */
    public function getValue(): ?string
    {
        $rf = $this->field->getRecipientField();

        return $rf !== null ? $rf->getValue() : null;
    }

    /**
     * Get the text value (for text, email, dropdown, and typed signature fields).
     */
    public function getText(): ?string
    {
        $rf = $this->field->getRecipientField();

        return $rf !== null ? $rf->getText() : null;
    }

    /**
     * Get the date value (for date fields).
     */
    public function getDate(): ?string
    {
        $rf = $this->field->getRecipientField();

        return $rf !== null ? $rf->getDate() : null;
    }

    /**
     * Get whether the checkbox is checked (for checkbox fields).
     */
    public function isChecked(): bool
    {
        $rf = $this->field->getRecipientField();

        return $rf !== null ? $rf->isChecked() : false;
    }

    /**
     * Get the signature/initials image path (for signature/initials fields).
     */
    public function getImage(): ?string
    {
        $rf = $this->field->getRecipientField();

        return $rf !== null ? $rf->getImage() : null;
    }

    public function getProperties(): FieldProperties
    {
        return $this->field->getProperties();
    }

    public function isRequired(): bool
    {
        return $this->field->isRequired();
    }

    public function isSignature(): bool
    {
        return $this->field->isSignature();
    }

    public function isInitials(): bool
    {
        return $this->field->isInitials();
    }

    public function getRecipientId(): int
    {
        return $this->recipient->getId();
    }

    public function getRecipientName(): string
    {
        return $this->recipient->getName();
    }

    public function getRecipientEmail(): string
    {
        return $this->recipient->getEmail();
    }

    public function getField(): Field
    {
        return $this->field;
    }

    /**
     * Get the raw submitted data for this field.
     */
    public function getRecipientField(): ?RecipientField
    {
        return $this->field->getRecipientField();
    }

    public function getRecipient(): Recipient
    {
        return $this->recipient;
    }

    /**
     * Convert to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'field_type_id' => $this->getFieldTypeId(),
            'field_type_name' => $this->getFieldTypeName(),
            'value' => $this->getValue(),
            'image' => $this->getImage(),
            'is_required' => $this->isRequired(),
            'is_signature' => $this->isSignature(),
            'is_initials' => $this->isInitials(),
            'recipient_id' => $this->getRecipientId(),
            'recipient_name' => $this->getRecipientName(),
            'recipient_email' => $this->getRecipientEmail(),
        ];
    }
}
