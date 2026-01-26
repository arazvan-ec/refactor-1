<?php

declare(strict_types=1);

namespace App\Exception\Multimedia;

use App\Exception\AbstractDomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a multimedia type is not supported.
 */
final class UnsupportedMultimediaTypeException extends AbstractDomainException
{
    protected int $statusCode = Response::HTTP_BAD_REQUEST;
    protected string $errorCode = 'UNSUPPORTED_MULTIMEDIA_TYPE';
    protected string $userMessage = 'The multimedia type is not supported';

    public function __construct(
        string $multimediaType,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Multimedia type "%s" is not supported', $multimediaType),
            $this->statusCode,
            $previous,
        );
    }
}
