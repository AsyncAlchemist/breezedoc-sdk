<?php

declare(strict_types=1);

namespace Breezedoc\Models;

use DateTimeImmutable;

/**
 * Represents a Breezedoc document.
 */
class Document extends AbstractModel
{
    private int $id;
    private string $title;
    private string $slug;
    private ?string $redirectUrl;
    private ?DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;
    private ?DateTimeImmutable $completedAt;

    /**
     * @var array<DocumentFile>
     */
    private array $documentFiles = [];

    /**
     * @var array<Field>
     */
    private array $fields = [];

    /**
     * @var array<Recipient>
     */
    private array $recipients = [];

    /**
     * @var array<SubmittedField>|null
     */
    private ?array $submittedFields = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $document = new self();
        $document->rawData = $data;

        $document->id = (int) $data['id'];
        $document->title = (string) $data['title'];
        $document->slug = (string) $data['slug'];
        $document->redirectUrl = $data['redirect_url'] ?? null;
        $document->createdAt = self::parseDateTime($data['created_at'] ?? null);
        $document->updatedAt = self::parseDateTime($data['updated_at'] ?? null);
        $document->completedAt = self::parseDateTime($data['completed_at'] ?? null);

        if (isset($data['document_files']) && is_array($data['document_files'])) {
            foreach ($data['document_files'] as $fileData) {
                $document->documentFiles[] = DocumentFile::fromArray($fileData);
            }
        }

        if (isset($data['fields']) && is_array($data['fields'])) {
            foreach ($data['fields'] as $fieldData) {
                $document->fields[] = Field::fromArray($fieldData);
            }
        }

        if (isset($data['recipients']) && is_array($data['recipients'])) {
            foreach ($data['recipients'] as $recipientData) {
                $document->recipients[] = Recipient::fromArray($recipientData);
            }
        }

        return $document;
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

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
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
     * @return array<DocumentFile>
     */
    public function getDocumentFiles(): array
    {
        return $this->documentFiles;
    }

    /**
     * @return array<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<Recipient>
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * Get all submitted field data across all recipients.
     *
     * Returns fields that have been submitted by recipients, combining
     * the field definition, submitted value, and recipient context.
     *
     * @return array<SubmittedField>
     */
    public function getSubmittedFields(): array
    {
        if ($this->submittedFields !== null) {
            return $this->submittedFields;
        }

        $recipientMap = [];
        foreach ($this->recipients as $recipient) {
            $recipientMap[$recipient->getId()] = $recipient;
        }

        $this->submittedFields = [];

        foreach ($this->fields as $field) {
            if ($field->isSubmitted() && $field->getRecipientId() !== null) {
                $recipient = $recipientMap[$field->getRecipientId()] ?? null;
                if ($recipient !== null) {
                    $this->submittedFields[] = SubmittedField::create($field, $recipient);
                }
            }
        }

        return $this->submittedFields;
    }

    /**
     * Get a submitted field by its field name.
     *
     * Returns the first matching field across all recipients. If the document
     * has multiple fields with the same name (e.g., for different parties),
     * use getSubmittedFields() and filter manually.
     */
    public function getSubmittedField(string $name): ?SubmittedField
    {
        foreach ($this->getSubmittedFields() as $submittedField) {
            if ($submittedField->getName() === $name) {
                return $submittedField;
            }
        }

        return null;
    }

    /**
     * Get submitted fields for a specific recipient.
     *
     * @return array<SubmittedField>
     */
    public function getSubmittedFieldsFor(Recipient $recipient): array
    {
        $result = [];

        foreach ($this->getSubmittedFields() as $submittedField) {
            if ($submittedField->getRecipientId() === $recipient->getId()) {
                $result[] = $submittedField;
            }
        }

        return $result;
    }
}
