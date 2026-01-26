<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for domain exceptions.
 *
 * Provides common functionality for domain-specific exceptions.
 */
abstract class AbstractDomainException extends \RuntimeException implements DomainExceptionInterface
{
    protected int $statusCode = Response::HTTP_BAD_REQUEST;
    protected string $errorCode = 'DOMAIN_ERROR';
    protected string $userMessage = 'An error occurred';

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?: $this->userMessage, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
