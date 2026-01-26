<?php

declare(strict_types=1);

namespace App\Exception\Multimedia;

use App\Exception\AbstractDomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when multimedia content is not found.
 */
final class MultimediaNotFoundException extends AbstractDomainException
{
    protected int $statusCode = Response::HTTP_NOT_FOUND;
    protected string $errorCode = 'MULTIMEDIA_NOT_FOUND';
    protected string $userMessage = 'The requested multimedia was not found';

    public function __construct(
        string $multimediaId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Multimedia with ID "%s" was not found', $multimediaId),
            $this->statusCode,
            $previous,
        );
    }
}
