<?php

declare(strict_types=1);

namespace App\Exception\Section;

use App\Exception\AbstractDomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a section is not found.
 */
final class SectionNotFoundException extends AbstractDomainException
{
    protected int $statusCode = Response::HTTP_NOT_FOUND;
    protected string $errorCode = 'SECTION_NOT_FOUND';
    protected string $userMessage = 'The requested section was not found';

    public function __construct(
        string $sectionId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Section with ID "%s" was not found', $sectionId),
            $this->statusCode,
            $previous,
        );
    }
}
