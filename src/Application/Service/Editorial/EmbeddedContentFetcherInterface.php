<?php

declare(strict_types=1);

namespace App\Application\Service\Editorial;

use App\Application\DTO\EmbeddedContentDTO;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Section\Domain\Model\Section;

/**
 * Fetches embedded content (inserted news, recommended editorials) for an editorial.
 *
 * Extracted from EditorialOrchestrator to improve single responsibility.
 */
interface EmbeddedContentFetcherInterface
{
    /**
     * Fetch all embedded content for an editorial.
     *
     * Includes:
     * - Inserted news (BodyTagInsertedNews elements)
     * - Recommended editorials
     * - Associated multimedia promises
     */
    public function fetch(NewsBase $editorial, Section $section): EmbeddedContentDTO;
}
