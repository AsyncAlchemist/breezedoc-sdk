<?php

declare(strict_types=1);

namespace Breezedoc\Webhooks;

use Breezedoc\Exceptions\WebhookVerificationException;
use DateTimeImmutable;

/**
 * A verified BreezeDoc webhook event.
 *
 * The documented payload is:
 *
 *     {"id": "evt_...", "type": "recipient.signed", "created_at": "2026-10-23T12:00:00Z",
 *      "data": {"document_id": "doc_456", "status": "sent",
 *               "recipient": {"id": "rcp_789", "email": "..."}}}
 *
 * Payloads hold metadata only, never the document text or field values. Deliveries can
 * repeat or arrive out of order: deduplicate by {@see getId()} in durable storage, and read
 * the current document status from the API when the order matters.
 */
class WebhookEvent
{
    private string $id;
    private string $type;
    private ?DateTimeImmutable $createdAt;

    /**
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * @var array<string, mixed>
     */
    private array $rawData;

    /**
     * Build an event from a decoded payload.
     *
     * @param array<string, mixed> $payload
     * @throws WebhookVerificationException If `id` or `type` is missing
     */
    public static function fromArray(array $payload): self
    {
        $id = $payload['id'] ?? null;
        $type = $payload['type'] ?? null;
        if (!is_string($id) || $id === '' || !is_string($type) || $type === '') {
            throw new WebhookVerificationException('The webhook payload has no event id or type.');
        }

        $event = new self();
        $event->rawData = $payload;
        $event->id = $id;
        $event->type = $type;
        $event->data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];

        $createdAt = $payload['created_at'] ?? null;
        $event->createdAt = null;
        if (is_string($createdAt) && $createdAt !== '') {
            try {
                $event->createdAt = new DateTimeImmutable($createdAt);
            } catch (\Exception $e) {
                $event->createdAt = null;
            }
        }

        return $event;
    }

    /**
     * The event ID (`evt_...`). Redeliveries keep the same ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * The event type, one of the {@see WebhookEventType} values.
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function is(string $type): bool
    {
        return $this->type === $type;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * The `data` object of the event.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * The document ID (`data.document_id`, for example "doc_456"), or null.
     */
    public function getDocumentId(): ?string
    {
        $id = $this->data['document_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * The document status at the time of the event (`data.status`), or null.
     */
    public function getDocumentStatus(): ?string
    {
        $status = $this->data['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    /**
     * The recipient of a `recipient.*` event (`data.recipient`), or null.
     */
    public function getRecipient(): ?WebhookRecipient
    {
        $recipient = $this->data['recipient'] ?? null;

        return is_array($recipient) ? WebhookRecipient::fromArray($recipient) : null;
    }

    public function isDocumentCompleted(): bool
    {
        return $this->type === WebhookEventType::DOCUMENT_COMPLETED;
    }

    public function isTest(): bool
    {
        return $this->type === WebhookEventType::WEBHOOK_TEST;
    }

    /**
     * The full decoded payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rawData;
    }
}
