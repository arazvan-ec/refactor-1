<?php

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps;

use App\Infrastructure\Service\Thumbor;
use App\Infrastructure\Trait\MultimediaTrait;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Encode\Encode;
use Ec\Multimedia\Domain\Model\Multimedia;
use Ec\Section\Domain\Model\Section;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class RecommendedEditorialsDataTransformer
{
    use UrlGeneratorTrait;
    use MultimediaTrait;
    /** @var string */
    private const TYPE = 'recommendededitorial';

    /** @var Editorial[] */
    private array $editorials;

    /** @var array<string, mixed> */
    private array $resolveData;

    public function __construct(
        string $extension,
        Thumbor $thumbor,
    ) {
        $this->setExtension($extension);
        $this->setThumbor($thumbor);
    }

    /**
     * @param Editorial[]          $editorials
     * @param array<string, mixed> $resolveData
     *
     * @return $this
     */
    public function write(array $editorials, array $resolveData = []): RecommendedEditorialsDataTransformer
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

            /** @var array<string, mixed> $recommendedEditorials */
            $recommendedEditorials = $this->resolveData['recommendedEditorials'];
            /** @var array<string, mixed> $currentRecommendedEditorial */
            $currentRecommendedEditorial = $recommendedEditorials[$editorialId];
            $signatures = $currentRecommendedEditorial['signatures'];

            /** @var Section $section */
            $section = $currentRecommendedEditorial['section'];

            $elementArray = [];
            $elementArray['type'] = self::TYPE;
            $elementArray['editorialId'] = $editorialId;
            $elementArray['signatures'] = $signatures;
            $elementArray['editorial'] = $this->editorialUrl($editorial, $section);
            $elementArray['title'] = $editorial->editorialTitles()->title();
            $shots = $this->getMultimedia($editorialId);

            $elementArray['shots'] = $shots;
            $elementArray['photo'] = empty($shots) ? '' : reset($shots);

            $recommended[] = $elementArray;
        }

        return $recommended;
    }

    private function editorialUrl(Editorial $editorial, Section $section): string
    {
        $editorialPath = $section->getPath().'/'.
            $editorial->publicationDate()->format('Y-m-d').'/'.
            Encode::encodeUrl($editorial->editorialTitles()->urlTitle()).'_'.
            $editorial->id()->id();

        return $this->generateUrl(
            'https://%s.%s.%s/%s',
            $section->isBlog() ? 'blog' : 'www',
            $section->siteId(),
            $editorialPath
        );
    }

    /**
     * @return array<string, string>
     */
    private function getMultimedia(string $editorialId): array
    {
        $shots = [];

        /** @var array<string, mixed> $recommendedEditorials */
        $recommendedEditorials = $this->resolveData['recommendedEditorials'];
        /** @var array<string, string> $currentRecommendedEditorial */
        $currentRecommendedEditorial = $recommendedEditorials[$editorialId];
        /** @var array<string, mixed> $multimediaData */
        $multimediaData = $this->resolveData['multimedia'];
        /** @var ?Multimedia $multimedia */
        $multimedia = $multimediaData[$currentRecommendedEditorial['multimediaId']] ?? null;
        if (null === $multimedia) {
            return $shots;
        }

        return $this->getShotsLandscape($multimedia);
    }
}
