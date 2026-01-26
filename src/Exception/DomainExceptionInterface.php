<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Marker interface for domain exceptions.
 *
 * Domain exceptions represent business rule violations or domain-specific errors.
 * They should contain appropriate HTTP status codes for API responses.
 */
interface DomainExceptionInterface extends \Throwable
{
    /**
     * Get the HTTP status code for this exception.
     */
    public function getStatusCode(): int;

    /**
     * Get a user-friendly error message.
     */
    public function getUserMessage(): string;

    /**
     * Get the error code for client identification.
     */
    public function getErrorCode(): string;
}
