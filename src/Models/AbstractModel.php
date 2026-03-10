<?php

declare(strict_types=1);

namespace Breezedoc\Models;

use DateTimeImmutable;

/**
 * Base model class with common functionality.
 */
abstract class AbstractModel
{
    /**
     * @var array<string, mixed>
     */
    protected array $rawData = [];

    /**
     * Create an instance from an array of data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    abstract public static function fromArray(array $data): self;

    /**
     * Convert the model to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rawData;
    }

    /**
     * Parse a datetime string into a DateTimeImmutable object.
     */
    protected static function parseDateTime(?string $datetime): ?DateTimeImmutable
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        return new DateTimeImmutable($datetime);
    }
}
