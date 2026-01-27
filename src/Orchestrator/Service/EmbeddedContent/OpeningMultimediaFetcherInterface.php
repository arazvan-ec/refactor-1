<?php

declare(strict_types=1);

namespace App\Orchestrator\Service\EmbeddedContent;

use Ec\Editorial\Domain\Model\NewsBase;

/**
 * Fetches opening multimedia for an editorial.
 */
interface OpeningMultimediaFetcherInterface
{
    /**
     * Fetch opening multimedia for the editorial.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetch(NewsBase $editorial): array;
}
