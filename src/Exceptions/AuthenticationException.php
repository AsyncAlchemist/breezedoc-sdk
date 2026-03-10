<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

use Throwable;

/**
 * Exception for authentication errors (HTTP 401).
 */
class AuthenticationException extends ApiException
{
    public function __construct(string $message = 'Unauthenticated', ?Throwable $previous = null)
    {
        parent::__construct($message, 401, $previous);
    }
}
