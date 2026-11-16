<?php

declare(strict_types=1);

namespace Breezedoc\Webhooks;

/**
 * The recipient of a `recipient.*` webhook event (`data.recipient`).
 */
class WebhookRecipient
{
    private ?string $id;
    private ?string $email;

    /**
     * @var array<string, mixed>
     */
    private array $rawData;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $recipient = new self();
        $recipient->rawData = $data;
        $recipient->id = isset($data['id']) && is_string($data['id']) ? $data['id'] : null;
        $recipient->email = isset($data['email']) && is_string($data['email']) ? $data['email'] : null;

        return $recipient;
    }

    /**
     * The recipient ID, for example "rcp_789".
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rawData;
    }
}
