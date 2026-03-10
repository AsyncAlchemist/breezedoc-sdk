<?php

declare(strict_types=1);

namespace Breezedoc\Models;

use DateTimeImmutable;

/**
 * Represents a Breezedoc user.
 */
class User extends AbstractModel
{
    private int $id;
    private string $name;
    private string $email;
    private ?DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $user = new self();
        $user->rawData = $data;

        $user->id = (int) $data['id'];
        $user->name = (string) $data['name'];
        $user->email = (string) $data['email'];
        $user->createdAt = self::parseDateTime($data['created_at'] ?? null);
        $user->updatedAt = self::parseDateTime($data['updated_at'] ?? null);

        return $user;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
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
