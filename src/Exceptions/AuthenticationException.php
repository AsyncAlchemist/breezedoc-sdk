<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

use Throwable;

/**
 * Exception for authentication errors (HTTP 401).
 */
class AuthenticationException extends ApiException
{
    /**
     * @param array<string, mixed>|null $responseBody
     */
    public function __construct(
        string $message = 'Unauthenticated',
        ?Throwable $previous = null,
        ?array $responseBody = null
    ) {
        parent::__construct($message, 401, $previous, $responseBody);
    }
}
