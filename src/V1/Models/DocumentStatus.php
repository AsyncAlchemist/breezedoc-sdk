<?php

declare(strict_types=1);

namespace Breezedoc\V1\Models;

/**
 * Document status values of the Team API v1.
 *
 * These are the values of the documented `status` filter of `GET /v1/documents`.
 */
final class DocumentStatus
{
    public const DRAFT = 'draft';
    public const SENT = 'sent';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    /**
     * @return array<string>
     */
    public static function all(): array
    {
        return [self::DRAFT, self::SENT, self::COMPLETED, self::CANCELLED, self::EXPIRED];
    }

    private function __construct()
    {
    }
}
