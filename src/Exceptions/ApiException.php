<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

use Throwable;

/**
 * Exception for API errors.
 *
 * The original API answers errors as `{"message": "..."}`. The Team API v1 answers them
 * as `{"error": {"code": "...", "message": "..."}}`; {@see getErrorCode()} returns that code.
 */
class ApiException extends BreezedocException
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $responseBody;

    /**
     * @param array<string, mixed>|null $responseBody
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?array $responseBody = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->responseBody = $responseBody;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->code;
    }

    /**
     * Get the response body.
     *
     * @return array<string, mixed>|null
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    /**
     * Get the machine-readable error code of a Team API v1 error envelope
     * (`error.code`), or null when the response has none.
     */
    public function getErrorCode(): ?string
    {
        $error = $this->responseBody['error'] ?? null;
        if (!is_array($error) || !isset($error['code']) || !is_string($error['code'])) {
            return null;
        }

        return $error['code'];
    }
}
