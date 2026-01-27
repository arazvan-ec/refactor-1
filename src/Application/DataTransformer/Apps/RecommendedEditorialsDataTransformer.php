<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps;

use App\Application\DataTransformer\DTO\MultimediaShotsCollectionDTO;
use App\Application\DataTransformer\DTO\MultimediaOpeningDTO;
use App\Application\DataTransformer\Service\MultimediaShotGenerator;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Encode\Encode;
use Ec\Multimedia\Domain\Model\Multimedia;
use Ec\Section\Domain\Model\Section;

/**
 * Transforms recommended editorials to API format.
 *
 * Refactored to use MultimediaShotGenerator instead of MultimediaTrait,
 * eliminating code duplication with BodyTagInsertedNewsDataTransformer.
 *
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class RecommendedEditorialsDataTransformer
{
    use UrlGeneratorTrait;

    private const TYPE = 'recommendededitorial';

    /** @var Editorial[] */
    private array $editorials;

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $resolveData;

    public function __construct(
        private readonly MultimediaShotGenerator $shotGenerator,
    ) {}

    /**
     * @param Editorial[]                                        $editorials
     * @param array<string, array<string, array<string, mixed>>> $resolveData
     */
    public function write(array $editorials, array $resolveData = []): self
    {
        $this->editorials = $editorials;
        $this->resolveData = $resolveData;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function read(): array
    {
        $recommended = [];

        foreach ($this->editorials as $editorial) {
            $editorialId = $editorial->id()->id();

            /** @var array<string, mixed> $currentRecommended */
            $currentRecommended = $this->resolveData['recommendedEditorials'][$editorialId];

            /** @var Section $section */
            $section = $currentRecommended['section'];

            $shots = $this->resolveShots($editorialId, $currentRecommended);

            $recommended[] = [
                'type' => self::TYPE,
                'editorialId' => $editorialId,
                'signatures' => $currentRecommended['signatures'],
                'editorial' => $this->buildEditorialUrl($editorial, $section),
                'title' => $editorial->editorialTitles()->title(),
                'shots' => $shots->toLegacyFormat(),
                'photo' => $shots->isEmpty() ? '' : $shots->all()[0]->url,
            ];
        }

        return $recommended;
    }

    /**
     * Resolve shots preferring opening multimedia over body multimedia.
     *
     * @param array<string, mixed> $recommendedData
     */
    private function resolveShots(string $editorialId, array $recommendedData): MultimediaShotsCollectionDTO
    {
        $multimediaId = $recommendedData['multimediaId'] ?? null;
        if ($multimediaId === null) {
            return new MultimediaShotsCollectionDTO();
        }

        // Prefer opening multimedia
        $openingShots = $this->resolveFromOpening($multimediaId);
        if (!$openingShots->isEmpty()) {
            return $openingShots;
        }

        // Fallback to body multimedia
        return $this->resolveFromMultimedia($multimediaId);
    }

    private function resolveFromOpening(string $multimediaId): MultimediaShotsCollectionDTO
    {
        /** @var ?array{opening: Multimedia\MultimediaPhoto, resource: \Ec\Multimedia\Domain\Model\Photo\Photo} $openingData */
        $openingData = $this->resolveData['multimediaOpening'][$multimediaId] ?? null;

        if ($openingData === null) {
            return new MultimediaShotsCollectionDTO();
        }

        $openingDTO = MultimediaOpeningDTO::fromArray($openingData);

        return $this->shotGenerator->generateLandscapeShotsFromOpening($openingDTO);
    }

    private function resolveFromMultimedia(string $multimediaId): MultimediaShotsCollectionDTO
    {
        /** @var ?Multimedia $multimedia */
        $multimedia = $this->resolveData['multimedia'][$multimediaId] ?? null;

        if ($multimedia === null) {
            return new MultimediaShotsCollectionDTO();
        }

        return $this->shotGenerator->generateLandscapeShots($multimedia);
    }

    private function buildEditorialUrl(Editorial $editorial, Section $section): string
    {
        $editorialPath = sprintf(
            '%s/%s/%s_%s',
            $section->getPath(),
            $editorial->publicationDate()->format('Y-m-d'),
            Encode::encodeUrl($editorial->editorialTitles()->urlTitle()),
            $editorial->id()->id(),
        );

        return $this->generateUrl(
            'https://%s.%s.%s/%s',
            $section->isSubdomainBlog() ? 'blog' : 'www',
            $section->siteId(),
            $editorialPath,
        );
    }
}
