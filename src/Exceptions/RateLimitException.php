<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

use Throwable;

/**
 * Exception for rate limit errors (HTTP 429).
 */
class RateLimitException extends ApiException
{
    private ?int $retryAfter;

    public function __construct(
        string $message = 'Rate Limit Exceeded',
        ?int $retryAfter = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 429, $previous);
        $this->retryAfter = $retryAfter;
    }

    /**
     * Get the number of seconds to wait before retrying.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
