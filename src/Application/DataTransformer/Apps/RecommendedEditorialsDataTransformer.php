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
use Ec\Section\Domain\Model\Section;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class RecommendedEditorialsDataTransformer
{
    use UrlGeneratorTrait;
    use MultimediaTrait;

    /** @var Editorial[] */
    private array $editorials;

    /** @var array<string, mixed> */
    private array $resolveData;

    public function __construct(
        string $extension,
        private readonly Thumbor $thumbor,
    ) {
        $this->setExtension($extension);
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

            $signatures = $this->resolveData()['recommendedEditorials'][$editorialId]['signatures'];

            /** @var Section $section */
            $section = $this->resolveData()['recommendedEditorials'][$editorialId]['section'];

            $elementArray = [];
            $elementArray['editorialId'] = $editorial->id()->id();
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

    /**
     * @return array<string, mixed>
     */
    public function resolveData(): array
    {
        return $this->resolveData;
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

        $multimedia = $this->resolveData()['multimedia'][$this->resolveData()['recommendedEditorials'][$editorialId]['multimediaId']] ?? null;
        if (null === $multimedia) {
            return $shots;
        }

        return $this->getShotsLandscape($multimedia);
    }
}
