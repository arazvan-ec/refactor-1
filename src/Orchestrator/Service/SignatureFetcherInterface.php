<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Section\Domain\Model\Section;

/**
 * Fetches and transforms journalist signatures for editorials.
 */
interface SignatureFetcherInterface
{
    /**
     * Fetch all signatures for an editorial.
     *
     * @return array<int, array{id: string, name: string, picture: string|null, url: string, twitter?: string}>
     */
    public function fetchSignatures(NewsBase $editorial, Section $section): array;
}
