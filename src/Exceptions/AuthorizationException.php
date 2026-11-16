<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

use Throwable;

/**
 * Exception for authorization errors (HTTP 403).
 */
class AuthorizationException extends ApiException
{
    /**
     * @param array<string, mixed>|null $responseBody
     */
    public function __construct(
        string $message = 'Forbidden',
        ?Throwable $previous = null,
        ?array $responseBody = null
    ) {
        parent::__construct($message, 403, $previous, $responseBody);
    }
}
