<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use App\Application\DTO\EmbeddedContentDTO;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Section\Domain\Model\Section;

/**
 * Fetches embedded content (inserted news, recommended editorials) for an editorial.
 *
 * Located in Orchestrator layer as it makes HTTP calls to external services.
 * This follows the architecture rule: HTTP calls belong in the Orchestrator layer.
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
