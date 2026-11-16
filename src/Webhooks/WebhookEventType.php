<?php

declare(strict_types=1);

namespace Breezedoc\Webhooks;

/**
 * BreezeDoc webhook event types.
 */
final class WebhookEventType
{
    /** The document has been sent. */
    public const DOCUMENT_SENT = 'document.sent';

    /** A recipient opened the document. */
    public const RECIPIENT_VIEWED = 'recipient.viewed';

    /** A recipient finished signing. */
    public const RECIPIENT_SIGNED = 'recipient.signed';

    /** All recipients signed and the completed PDF is stored. */
    public const DOCUMENT_COMPLETED = 'document.completed';

    /** An integration (the API or an AI assistant) cancelled the document. */
    public const DOCUMENT_CANCELLED = 'document.cancelled';

    /** An incomplete document expired. */
    public const DOCUMENT_EXPIRED = 'document.expired';

    /** A test delivery that the team owner requested. */
    public const WEBHOOK_TEST = 'webhook.test';

    /**
     * The event types that `POST /v1/webhooks` accepts in `events`.
     *
     * @return array<string>
     */
    public static function subscribable(): array
    {
        return [
            self::DOCUMENT_SENT,
            self::RECIPIENT_VIEWED,
            self::RECIPIENT_SIGNED,
            self::DOCUMENT_COMPLETED,
            self::DOCUMENT_CANCELLED,
            self::DOCUMENT_EXPIRED,
        ];
    }

    /**
     * All documented event types, including `webhook.test`.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return array_merge(self::subscribable(), [self::WEBHOOK_TEST]);
    }

    private function __construct()
    {
    }
}
