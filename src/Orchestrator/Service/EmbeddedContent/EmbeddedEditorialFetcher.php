<?php

declare(strict_types=1);

namespace App\Orchestrator\Service\EmbeddedContent;

use App\Application\DTO\EmbeddedEditorialDTO;
use App\Infrastructure\Trait\MultimediaTrait;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use Psr\Log\LoggerInterface;

/**
 * Fetches a single embedded editorial with all its related data.
 *
 * Handles the common logic for fetching editorial, section, signatures,
 * and multimedia data for embedded content (inserted news, recommended).
 */
final class EmbeddedEditorialFetcher implements EmbeddedEditorialFetcherInterface
{
    use MultimediaTrait;

    private const ASYNC = true;

    public function __construct(
        private readonly QueryEditorialClient $editorialClient,
        private readonly QuerySectionClient $sectionClient,
        private readonly QueryJournalistClient $journalistClient,
        private readonly JournalistFactory $journalistFactory,
        private readonly QueryMultimediaClient $multimediaClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(string $editorialId): ?array
    {
        try {
            /** @var Editorial $editorial */
            $editorial = $this->editorialClient->findEditorialById($editorialId);

            if (!$editorial->isVisible()) {
                return null;
            }

            /** @var Section $section */
            $section = $this->sectionClient->findSectionById($editorial->sectionId());

            $signatures = $this->fetchSignatures($editorial, $section);
            [$multimediaId, $promises] = $this->collectMultimediaData($editorial);

            $dto = new EmbeddedEditorialDTO(
                id: $editorialId,
                editorial: $editorial,
                section: $section,
                signatures: $signatures,
                multimediaId: $multimediaId,
            );

            return ['dto' => $dto, 'promises' => $promises];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch embedded editorial: ' . $e->getMessage(), [
                'editorial_id' => $editorialId,
            ]);

            return null;
        }
    }

    /**
     * Fetch signatures for an editorial.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchSignatures(Editorial $editorial, Section $section): array
    {
        $signatures = [];

        /** @var Signature $signature */
        foreach ($editorial->signatures()->getArrayCopy() as $signature) {
            $result = $this->fetchSignatureData($signature->id()->id(), $section);

            if (!empty($result)) {
                $signatures[] = $result;
            }
        }

        return $signatures;
    }

    /**
     * Fetch data for a single signature.
     *
     * @return array<string, mixed>
     */
    private function fetchSignatureData(string $aliasId, Section $section): array
    {
        try {
            $aliasIdModel = $this->journalistFactory->buildAliasId($aliasId);
            $journalist = $this->journalistClient->findJournalistByAliasId($aliasIdModel);

            return [
                'aliasId' => $aliasId,
                'journalist' => $journalist,
                'section' => $section,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch signature: ' . $e->getMessage(), [
                'alias_id' => $aliasId,
            ]);

            return [];
        }
    }

    /**
     * Collect multimedia data (ID and async promise) for an editorial.
     *
     * @return array{0: ?string, 1: array<int, mixed>}
     */
    private function collectMultimediaData(Editorial $editorial): array
    {
        $promises = [];
        $multimediaId = null;

        if (!empty($editorial->multimedia()->id()->id())) {
            $multimediaId = $editorial->multimedia()->id()->id();
            $id = $this->getMultimediaId($editorial->multimedia());

            if (null !== $id) {
                $promises[] = $this->multimediaClient->findMultimediaById($id, self::ASYNC);
            }
        } elseif (!empty($editorial->metaImage())) {
            $multimediaId = $editorial->metaImage();
        }

        return [$multimediaId, $promises];
    }
}
