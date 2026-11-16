<?php

declare(strict_types=1);

namespace Breezedoc\V1\Models;

/**
 * A recipient (`rcp_...`) of a Team API v1 document.
 */
class TeamRecipient extends AbstractResource
{
    private string $id;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $recipient = new self();
        $recipient->rawData = $data;
        $recipient->id = self::idOf($data);

        return $recipient;
    }

    /**
     * The recipient ID, for example "rcp_789" ('' when the response has none).
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getParty(): ?int
    {
        return self::intOrNull($this->rawData['party'] ?? null);
    }

    public function getName(): ?string
    {
        return self::stringOrNull($this->rawData['name'] ?? null);
    }

    public function getEmail(): ?string
    {
        return self::stringOrNull($this->rawData['email'] ?? null);
    }

    public function getStatus(): ?string
    {
        return self::stringOrNull($this->rawData['status'] ?? null);
    }
}
