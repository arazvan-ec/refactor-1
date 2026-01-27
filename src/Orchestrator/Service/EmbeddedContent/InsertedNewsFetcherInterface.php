<?php

declare(strict_types=1);

namespace App\Orchestrator\Service\EmbeddedContent;

use App\Application\DTO\EmbeddedEditorialDTO;
use Ec\Editorial\Domain\Model\NewsBase;

/**
 * Fetches inserted news (BodyTagInsertedNews) from an editorial's body.
 */
interface InsertedNewsFetcherInterface
{
    /**
     * Fetch all inserted news from editorial body.
     *
     * @return array{editorials: array<string, EmbeddedEditorialDTO>, promises: array<int, mixed>}
     */
    public function fetch(NewsBase $editorial): array;
}
