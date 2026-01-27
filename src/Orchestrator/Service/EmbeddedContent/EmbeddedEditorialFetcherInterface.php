<?php

declare(strict_types=1);

namespace App\Orchestrator\Service\EmbeddedContent;

use App\Application\DTO\EmbeddedEditorialDTO;

/**
 * Fetches a single embedded editorial with all its related data.
 */
interface EmbeddedEditorialFetcherInterface
{
    /**
     * Fetch a single embedded editorial with section, signatures, and multimedia.
     *
     * @return array{dto: EmbeddedEditorialDTO, promises: array<int, mixed>}|null
     */
    public function fetch(string $editorialId): ?array;
}
