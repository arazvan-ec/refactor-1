<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use App\Application\DTO\EmbeddedContentDTO;
use App\Application\DTO\EmbeddedEditorialDTO;
use App\Infrastructure\Trait\MultimediaTrait;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\Multimedia\Multimedia;
use Ec\Editorial\Domain\Model\Multimedia\Widget;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia as AbstractMultimedia;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Infrastructure\Client\Http\Media\QueryMultimediaClient as QueryMultimediaOpeningClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use Psr\Log\LoggerInterface;

/**
 * Fetches embedded content (inserted news, recommended editorials) for an editorial.
 *
 * Located in Orchestrator layer because it makes HTTP calls via:
 * - QueryEditorialClient
 * - QuerySectionClient
 * - QueryMultimediaClient
 * - QueryMultimediaOpeningClient
 * - QueryJournalistClient
 *
 * This follows the architecture rule: HTTP calls belong in the Orchestrator layer,
 * NOT in the Application/transformation layer.
 */
final class EmbeddedContentFetcher implements EmbeddedContentFetcherInterface
{
    use MultimediaTrait;

    private const ASYNC = true;

    public function __construct(
        private readonly QueryEditorialClient $editorialClient,
        private readonly QuerySectionClient $sectionClient,
        private readonly QueryMultimediaClient $multimediaClient,
        private readonly QueryMultimediaOpeningClient $multimediaOpeningClient,
        private readonly QueryJournalistClient $journalistClient,
        private readonly JournalistFactory $journalistFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(NewsBase $editorial, Section $section): EmbeddedContentDTO
    {
        $insertedNews = $this->fetchInsertedNews($editorial);
        $recommendedContent = $this->fetchRecommendedEditorials($editorial);
        $openingData = $this->fetchOpeningMultimedia($editorial);
        $mainMultimedia = $this->fetchMainMultimedia($editorial);

        return new EmbeddedContentDTO(
            insertedNews: $insertedNews['editorials'],
            recommendedEditorials: $recommendedContent['editorials'],
            recommendedNews: $recommendedContent['news'],
            multimediaPromises: array_merge(
                $insertedNews['promises'],
                $recommendedContent['promises'],
                $mainMultimedia['promises']
            ),
            multimediaOpening: array_merge($openingData, $mainMultimedia['opening']),
        );
    }

    /**
     * Fetch all inserted news from editorial body.
     *
     * @return array{editorials: array<string, EmbeddedEditorialDTO>, promises: array<int, mixed>}
     */
    private function fetchInsertedNews(NewsBase $editorial): array
    {
        $editorials = [];
        $promises = [];

        /** @var BodyTagInsertedNews[] $insertedNewsTags */
        $insertedNewsTags = $editorial->body()->bodyElementsOf(BodyTagInsertedNews::class);

        foreach ($insertedNewsTags as $insertedNewsTag) {
            $result = $this->fetchEmbeddedEditorial($insertedNewsTag->editorialId()->id());

            if (null !== $result) {
                $editorials[$result['dto']->id] = $result['dto'];
                $promises = array_merge($promises, $result['promises']);
            }
        }

        return ['editorials' => $editorials, 'promises' => $promises];
    }

    /**
     * Fetch all recommended editorials.
     *
     * @return array{editorials: array<string, EmbeddedEditorialDTO>, news: array<int, Editorial>, promises: array<int, mixed>}
     */
    private function fetchRecommendedEditorials(NewsBase $editorial): array
    {
        $editorials = [];
        $news = [];
        $promises = [];

        /** @var EditorialId $recommendedId */
        foreach ($editorial->recommendedEditorials()->editorialIds() as $recommendedId) {
            try {
                $result = $this->fetchEmbeddedEditorial($recommendedId->id());

                if (null !== $result) {
                    $editorials[$result['dto']->id] = $result['dto'];
                    $news[] = $result['dto']->editorial;
                    $promises = array_merge($promises, $result['promises']);
                }
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
            }
        }

        return ['editorials' => $editorials, 'news' => $news, 'promises' => $promises];
    }

    /**
     * Fetch a single embedded editorial with all its data.
     *
     * @return array{dto: EmbeddedEditorialDTO, promises: array<int, mixed>}|null
     */
    private function fetchEmbeddedEditorial(string $editorialId): ?array
    {
        try {
            /** @var Editorial $embeddedEditorial */
            $embeddedEditorial = $this->editorialClient->findEditorialById($editorialId);

            if (!$embeddedEditorial->isVisible()) {
                return null;
            }

            /** @var Section $section */
            $section = $this->sectionClient->findSectionById($embeddedEditorial->sectionId());

            $signatures = $this->fetchSignatures($embeddedEditorial, $section);
            [$multimediaId, $promises] = $this->collectMultimediaData($embeddedEditorial);

            $dto = new EmbeddedEditorialDTO(
                id: $editorialId,
                editorial: $embeddedEditorial,
                section: $section,
                signatures: $signatures,
                multimediaId: $multimediaId,
            );

            return ['dto' => $dto, 'promises' => $promises];
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

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

            // Return raw data - transformation happens in ResponseAggregator
            return [
                'aliasId' => $aliasId,
                'journalist' => $journalist,
                'section' => $section,
            ];
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

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
            // Meta image handling is synchronous in the original code
        }

        return [$multimediaId, $promises];
    }

    /**
     * Fetch opening multimedia for the main editorial.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchOpeningMultimedia(NewsBase $editorial): array
    {
        $opening = $editorial->opening();

        if (empty($opening->multimediaId())) {
            return [];
        }

        try {
            /** @var AbstractMultimedia $multimedia */
            $multimedia = $this->multimediaOpeningClient->findMultimediaById($opening->multimediaId());

            // The MultimediaOrchestratorHandler is called in the original code
            // We return the raw multimedia for now
            return ['opening' => ['multimedia' => $multimedia]];
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage());

            return [];
        }
    }

    /**
     * Fetch main multimedia for the editorial.
     *
     * @return array{promises: array<int, mixed>, opening: array<string, array<string, mixed>>}
     */
    private function fetchMainMultimedia(NewsBase $editorial): array
    {
        $promises = [];
        $opening = [];

        $multimedia = $editorial->multimedia();

        if ($multimedia instanceof Widget) {
            return ['promises' => [], 'opening' => []];
        }

        $id = $this->getMultimediaId($multimedia);

        if (null !== $id) {
            $promises[] = $this->multimediaClient->findMultimediaById($id, self::ASYNC);
        }

        // Handle meta image fallback
        if (empty($id) && !empty($editorial->metaImage())) {
            try {
                /** @var Multimedia $metaMultimedia */
                $metaMultimedia = $this->multimediaOpeningClient->findMultimediaById($editorial->metaImage());

                if ($metaMultimedia instanceof MultimediaPhoto) {
                    $resource = $this->multimediaOpeningClient->findPhotoById($metaMultimedia->resourceId());
                    $opening[$editorial->metaImage()] = [
                        'resource' => $resource,
                        'opening' => $metaMultimedia,
                    ];
                }
            } catch (\Throwable $e) {
                $this->logger->warning($e->getMessage());
            }
        }

        return ['promises' => $promises, 'opening' => $opening];
    }
}
