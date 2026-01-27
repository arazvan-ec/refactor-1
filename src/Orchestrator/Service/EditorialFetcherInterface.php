<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use App\Application\DTO\FetchedEditorialDTO;
use Ec\Editorial\Domain\Model\NewsBase;

/**
 * Fetches editorial and associated data from external services.
 *
 * Located in Orchestrator layer as it makes HTTP calls to external services.
 * This follows the architecture rule: HTTP calls belong in the Orchestrator layer.
 */
interface EditorialFetcherInterface
{
    /**
     * Fetch editorial by ID with its associated section.
     *
     * @throws \App\Exception\EditorialNotPublishedYetException When editorial is not visible
     */
    public function fetch(string $editorialId): FetchedEditorialDTO;

    /**
     * Fetch editorial from legacy system.
     *
     * @return array<string, mixed>
     */
    public function fetchLegacy(string $editorialId): array;

    /**
     * Determine if legacy fallback should be used.
     */
    public function shouldUseLegacy(NewsBase $editorial): bool;
}
