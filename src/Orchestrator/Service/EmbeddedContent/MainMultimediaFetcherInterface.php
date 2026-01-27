<?php

declare(strict_types=1);

namespace App\Orchestrator\Service\EmbeddedContent;

use Ec\Editorial\Domain\Model\NewsBase;

/**
 * Fetches main multimedia for an editorial.
 */
interface MainMultimediaFetcherInterface
{
    /**
     * Fetch main multimedia for the editorial.
     *
     * @return array{promises: array<int, mixed>, opening: array<string, array<string, mixed>>}
     */
    public function fetch(NewsBase $editorial): array;
}
