<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

use Throwable;

/**
 * Exception for API errors.
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
}
