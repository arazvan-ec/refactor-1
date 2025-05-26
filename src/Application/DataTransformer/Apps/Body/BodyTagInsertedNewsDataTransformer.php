<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Body;

use App\Infrastructure\Service\Thumbor;
use App\Infrastructure\Trait\MultimediaTrait;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Assert\Assertion;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Encode\Encode;
use Ec\Section\Domain\Model\Section;

/**
 * @author Jose Guillermo Moreu Peso <jgmoreu@ext.elconfidencial.com>
 */
class BodyTagInsertedNewsDataTransformer extends ElementTypeDataTransformer
{
    use UrlGeneratorTrait;
    use MultimediaTrait;

    public function __construct(
        Thumbor $thumbor,
        string $extension,
    ) {
        $this->setExtension($extension);
        $this->setThumbor($thumbor);
    }

    public function canTransform(): string
    {
        return BodyTagInsertedNews::class;
    }

    public function read(): array
    {
        $message = 'BodyElement should be instance of '.BodyTagInsertedNews::class;
        /** @var BodyTagInsertedNews $bodyElement */
        $bodyElement = $this->bodyElement;
        Assertion::isInstanceOf($bodyElement, BodyTagInsertedNews::class, $message);

        $elementArray = parent::read();

        $editorialId = $bodyElement->editorialId()->id();

        /** @var array<string, array<string, mixed>> $resolveData */
        $resolveData = $this->resolveData();
        /** @var array<string, mixed> $currentInsertedNuews */
        $currentInsertedNuews = $resolveData['insertedNews'][$editorialId];
        $signatures = $currentInsertedNuews['signatures'];

        /** @var Editorial $editorial */
        $editorial = $currentInsertedNuews['editorial'];
        /** @var Section $sectionInserted */
        $sectionInserted = $currentInsertedNuews['section'];

        $elementArray['editorialId'] = $editorial->id()->id();
        $elementArray['title'] = $editorial->editorialTitles()->title();
        $elementArray['signatures'] = $signatures;
        $elementArray['editorial'] = $this->editorialUrl($editorial, $sectionInserted);

        $shots = $this->getMultimedia($editorialId);

        $elementArray['shots'] = $shots;
        $elementArray['photo'] = empty($shots) ? '' : reset($shots);

        return $elementArray;
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

        $multimedia = $this->resolveData()['multimedia'][$this->resolveData()['insertedNews'][$editorialId]['multimediaId']] ?? null;
        if (null === $multimedia) {
            return $shots;
        }

        return $this->getShotsLandscape($multimedia);
    }
}
