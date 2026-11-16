<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

use Throwable;

/**
 * Exception for validation errors (HTTP 422).
 */
class ValidationException extends ApiException
{
    /**
     * @var array<string, array<string>>
     */
    private array $errors;

    /**
     * @param array<string, array<string>> $errors Field validation errors
     * @param array<string, mixed>|null $responseBody
     */
    public function __construct(
        string $message = 'Validation Error',
        array $errors = [],
        ?Throwable $previous = null,
        ?array $responseBody = null
    ) {
        parent::__construct($message, 422, $previous, $responseBody);
        $this->errors = $errors;
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if a specific field has errors.
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get errors for a specific field.
     *
     * @return array<string>|null
     */
    public function getError(string $field): ?array
    {
        return $this->errors[$field] ?? null;
    }
}
