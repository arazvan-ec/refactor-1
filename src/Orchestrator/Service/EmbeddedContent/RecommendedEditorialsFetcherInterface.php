<?php

declare(strict_types=1);

namespace App\Orchestrator\Service\EmbeddedContent;

use App\Application\DTO\EmbeddedEditorialDTO;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\NewsBase;

/**
 * Fetches recommended editorials for an editorial.
 */
interface RecommendedEditorialsFetcherInterface
{
    /**
     * Fetch all recommended editorials.
     *
     * @return array{editorials: array<string, EmbeddedEditorialDTO>, news: array<int, Editorial>, promises: array<int, mixed>}
     */
    public function fetch(NewsBase $editorial): array;
}
