<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

use Throwable;

/**
 * Exception for not found errors (HTTP 404).
 */
class NotFoundException extends ApiException
{
    /**
     * @param array<string, mixed>|null $responseBody
     */
    public function __construct(
        string $message = 'Not Found',
        ?Throwable $previous = null,
        ?array $responseBody = null
    ) {
        parent::__construct($message, 404, $previous, $responseBody);
    }
}
