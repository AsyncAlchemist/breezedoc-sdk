<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

/**
 * A webhook request failed verification, or its verified payload is not a valid event.
 *
 * Thrown by {@see \Breezedoc\Webhooks\WebhookVerifier}. Reject the request (for example
 * with HTTP 400) and do not process it.
 */
class WebhookVerificationException extends BreezedocException
{
}
