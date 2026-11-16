<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

use Throwable;

/**
 * Exception for conflict errors (HTTP 409).
 *
 * The Team API v1 answers 409 for a state conflict (for example, an action that the
 * document's status does not allow) and when an `Idempotency-Key` is reused with a
 * different request.
 */
class ConflictException extends ApiException
{
    /**
     * @param array<string, mixed>|null $responseBody
     */
    public function __construct(
        string $message = 'Conflict',
        ?Throwable $previous = null,
        ?array $responseBody = null
    ) {
        parent::__construct($message, 409, $previous, $responseBody);
    }
}
