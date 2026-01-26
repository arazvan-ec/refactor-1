<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when an external service fails.
 */
final class ExternalServiceException extends AbstractDomainException
{
    protected int $statusCode = Response::HTTP_SERVICE_UNAVAILABLE;
    protected string $errorCode = 'EXTERNAL_SERVICE_ERROR';
    protected string $userMessage = 'An external service is temporarily unavailable';

    public function __construct(
        string $serviceName,
        string $reason = '',
        ?\Throwable $previous = null,
    ) {
        $message = $reason
            ? sprintf('External service "%s" failed: %s', $serviceName, $reason)
            : sprintf('External service "%s" is unavailable', $serviceName);

        parent::__construct($message, $this->statusCode, $previous);
    }
}
