<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Exceptions;

use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\AuthenticationException;
use Breezedoc\Exceptions\AuthorizationException;
use Breezedoc\Exceptions\BreezedocException;
use Breezedoc\Exceptions\NotFoundException;
use Breezedoc\Exceptions\RateLimitException;
use Breezedoc\Exceptions\ValidationException;
use Breezedoc\Tests\Unit\UnitTestCase;
use Exception;

class ExceptionTest extends UnitTestCase
{
    public function testBreezedocExceptionIsBaseException(): void
    {
        $exception = new BreezedocException('Test error');

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame('Test error', $exception->getMessage());
    }

    public function testApiExceptionExtendsBreezedocException(): void
    {
        $exception = new ApiException('API error', 500);

        $this->assertInstanceOf(BreezedocException::class, $exception);
        $this->assertSame('API error', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
    }

    public function testApiExceptionWithStatusCode(): void
    {
        $exception = new ApiException('Server error', 503);

        $this->assertSame(503, $exception->getStatusCode());
    }

    public function testApiExceptionWithResponseBody(): void
    {
        $body = ['message' => 'Internal server error'];
        $exception = new ApiException('Server error', 500, null, $body);

        $this->assertSame($body, $exception->getResponseBody());
    }

    public function testAuthenticationExceptionExtendsApiException(): void
    {
        $exception = new AuthenticationException('Invalid token');

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame(401, $exception->getStatusCode());
    }

    public function testAuthenticationExceptionDefaultMessage(): void
    {
        $exception = new AuthenticationException();

        $this->assertSame('Unauthenticated', $exception->getMessage());
    }

    public function testAuthorizationExceptionExtendsApiException(): void
    {
        $exception = new AuthorizationException('Access denied');

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame(403, $exception->getStatusCode());
    }

    public function testAuthorizationExceptionDefaultMessage(): void
    {
        $exception = new AuthorizationException();

        $this->assertSame('Forbidden', $exception->getMessage());
    }

    public function testNotFoundExceptionExtendsApiException(): void
    {
        $exception = new NotFoundException('Document not found');

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame(404, $exception->getStatusCode());
    }

    public function testNotFoundExceptionDefaultMessage(): void
    {
        $exception = new NotFoundException();

        $this->assertSame('Not Found', $exception->getMessage());
    }

    public function testValidationExceptionExtendsApiException(): void
    {
        $exception = new ValidationException('Validation failed');

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame(422, $exception->getStatusCode());
    }

    public function testValidationExceptionWithErrors(): void
    {
        $errors = [
            'title' => ['The title field is required.'],
            'email' => ['The email must be a valid email address.'],
        ];
        $exception = new ValidationException('Validation failed', $errors);

        $this->assertSame($errors, $exception->getErrors());
    }

    public function testValidationExceptionHasError(): void
    {
        $errors = [
            'title' => ['The title field is required.'],
        ];
        $exception = new ValidationException('Validation failed', $errors);

        $this->assertTrue($exception->hasError('title'));
        $this->assertFalse($exception->hasError('email'));
    }

    public function testValidationExceptionGetError(): void
    {
        $errors = [
            'title' => ['The title field is required.', 'The title may not be greater than 191 characters.'],
        ];
        $exception = new ValidationException('Validation failed', $errors);

        $this->assertSame($errors['title'], $exception->getError('title'));
        $this->assertNull($exception->getError('email'));
    }

    public function testValidationExceptionDefaultMessage(): void
    {
        $exception = new ValidationException();

        $this->assertSame('Validation Error', $exception->getMessage());
    }

    public function testRateLimitExceptionExtendsApiException(): void
    {
        $exception = new RateLimitException('Rate limit exceeded');

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame(429, $exception->getStatusCode());
    }

    public function testRateLimitExceptionWithRetryAfter(): void
    {
        $exception = new RateLimitException('Rate limit exceeded', 60);

        $this->assertSame(60, $exception->getRetryAfter());
    }

    public function testRateLimitExceptionDefaultRetryAfter(): void
    {
        $exception = new RateLimitException();

        $this->assertNull($exception->getRetryAfter());
    }

    public function testRateLimitExceptionDefaultMessage(): void
    {
        $exception = new RateLimitException();

        $this->assertSame('Rate Limit Exceeded', $exception->getMessage());
    }

    public function testExceptionChaining(): void
    {
        $previous = new Exception('Original error');
        $exception = new ApiException('API error', 500, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
