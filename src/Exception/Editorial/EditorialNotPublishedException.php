<?php

declare(strict_types=1);

namespace App\Exception\Editorial;

use App\Exception\AbstractDomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when an editorial is not yet published.
 */
final class EditorialNotPublishedException extends AbstractDomainException
{
    protected int $statusCode = Response::HTTP_NOT_FOUND;
    protected string $errorCode = 'EDITORIAL_NOT_PUBLISHED';
    protected string $userMessage = 'The editorial is not yet published';

    public function __construct(
        string $editorialId = '',
        ?\Throwable $previous = null,
    ) {
        $message = $editorialId
            ? sprintf('Editorial "%s" is not yet published', $editorialId)
            : 'Editorial not published';

        parent::__construct($message, $this->statusCode, $previous);
    }
}
