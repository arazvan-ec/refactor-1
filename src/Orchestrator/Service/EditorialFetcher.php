<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use App\Application\DTO\FetchedEditorialDTO;
use App\Exception\EditorialNotPublishedYetException;
use App\Infrastructure\Client\Legacy\QueryLegacyClient;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;

/**
 * Fetches editorial and associated data from external services.
 *
 * Located in Orchestrator layer because it makes HTTP calls via:
 * - QueryEditorialClient
 * - QuerySectionClient
 * - QueryLegacyClient
 *
 * This follows the architecture rule: HTTP calls belong in the Orchestrator layer,
 * NOT in the Application/transformation layer.
 */
final class EditorialFetcher implements EditorialFetcherInterface
{
    public function __construct(
        private readonly QueryEditorialClient $editorialClient,
        private readonly QuerySectionClient $sectionClient,
        private readonly QueryLegacyClient $legacyClient,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(string $editorialId): FetchedEditorialDTO
    {
        /** @var NewsBase $editorial */
        $editorial = $this->editorialClient->findEditorialById($editorialId);

        $this->ensureEditorialIsVisible($editorial);

        /** @var Section $section */
        $section = $this->sectionClient->findSectionById($editorial->sectionId());

        return new FetchedEditorialDTO(
            editorial: $editorial,
            section: $section,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function fetchLegacy(string $editorialId): array
    {
        return $this->legacyClient->findEditorialById($editorialId);
    }

    /**
     * {@inheritDoc}
     */
    public function shouldUseLegacy(NewsBase $editorial): bool
    {
        return null === $editorial->sourceEditorial();
    }

    /**
     * Ensure the editorial is visible (published).
     *
     * @throws EditorialNotPublishedYetException
     */
    private function ensureEditorialIsVisible(NewsBase $editorial): void
    {
        if (!$editorial->isVisible()) {
            throw new EditorialNotPublishedYetException();
        }
    }
}
