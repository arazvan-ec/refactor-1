<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Section\Domain\Model\Section;
use GuzzleHttp\Promise\PromiseInterface;

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

    /**
     * Fetch all signatures for an editorial asynchronously.
     *
     * @return PromiseInterface<array<int, array{id: string, name: string, picture: string|null, url: string, twitter?: string}>>
     */
    public function fetchSignaturesAsync(NewsBase $editorial, Section $section): PromiseInterface;
}
