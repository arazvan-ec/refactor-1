<?php

declare(strict_types=1);

namespace App\Exception\Editorial;

use App\Exception\AbstractDomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when an editorial is not found.
 */
final class EditorialNotFoundException extends AbstractDomainException
{
    protected int $statusCode = Response::HTTP_NOT_FOUND;
    protected string $errorCode = 'EDITORIAL_NOT_FOUND';
    protected string $userMessage = 'The requested editorial was not found';

    public function __construct(
        string $editorialId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Editorial with ID "%s" was not found', $editorialId),
            $this->statusCode,
            $previous,
        );
    }
}
