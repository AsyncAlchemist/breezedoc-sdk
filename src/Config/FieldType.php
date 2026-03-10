<?php

declare(strict_types=1);

namespace Breezedoc\Config;

/**
 * Field type UUID constants for Breezedoc document fields.
 *
 * These UUIDs are used to identify the type of field on a document.
 */
class FieldType
{
    public const SIGNATURE = '13eb6f62-cc8b-466a-9e25-400eb8f596aa';
    public const INITIALS = '195ff45a-6a44-40a6-b9c6-d24d69b6aac6';
    public const DATE = 'c96b9268-7266-4304-a0c3-2dc058c87a84';
    public const TEXT = 'c8ca9a67-4f54-4429-a409-ac58418bd1bc';
    public const EMAIL = 'e3b2c44d-5f6a-4e8b-9d1c-2f3e4a5b6c7d';
    public const DROPDOWN = '6f0fbecf-3bba-4b8a-87b0-b7eb49662661';
    public const CHECKBOX = '6bcdb12d-7364-4ada-9427-13831827a995';

    /**
     * @var array<string, string>
     */
    private static array $names = [
        self::SIGNATURE => 'Signature',
        self::INITIALS => 'Initials',
        self::DATE => 'Date',
        self::TEXT => 'Text',
        self::EMAIL => 'Email',
        self::DROPDOWN => 'Dropdown',
        self::CHECKBOX => 'Checkbox',
    ];

    /**
     * Get all field type UUIDs.
     *
     * @return array<string>
     */
    public static function getAll(): array
    {
        return array_keys(self::$names);
    }

    /**
     * Get the human-readable name for a field type UUID.
     */
    public static function getName(string $typeId): ?string
    {
        return self::$names[$typeId] ?? null;
    }

    /**
     * Check if a UUID is a valid field type.
     */
    public static function isValid(string $typeId): bool
    {
        return isset(self::$names[$typeId]);
    }
}
