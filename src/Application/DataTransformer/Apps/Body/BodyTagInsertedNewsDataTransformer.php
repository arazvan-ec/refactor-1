<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Adapter\LegacyResolveDataAdapter;
use App\Application\DataTransformer\Service\MultimediaShotResolver;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Assert\Assertion;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Exceptions\BodyDataTransformerNotFoundException;
use Ec\Encode\Encode;
use Ec\Section\Domain\Model\Section;

/**
 * Transforms BodyTagInsertedNews elements to API format.
 *
 * Refactored to use MultimediaShotResolver instead of MultimediaTrait,
 * eliminating code duplication with RecommendedEditorialsDataTransformer.
 *
 * @author Jose Guillermo Moreu Peso <jgmoreu@ext.elconfidencial.com>
 */
class BodyTagInsertedNewsDataTransformer extends ElementTypeDataTransformer
{
    use UrlGeneratorTrait;

    public function __construct(
        private readonly MultimediaShotResolver $shotResolver,
    ) {}

    public function canTransform(): string
    {
        return BodyTagInsertedNews::class;
    }

    public function read(): array
    {
        /** @var BodyTagInsertedNews $bodyElement */
        $bodyElement = $this->bodyElement;
        Assertion::isInstanceOf(
            $bodyElement,
            BodyTagInsertedNews::class,
            sprintf('BodyElement should be instance of %s', BodyTagInsertedNews::class),
        );

        $elementArray = parent::read();
        $resolveData = LegacyResolveDataAdapter::ensureDTO($this->resolveData());

        $editorialId = $bodyElement->editorialId()->id();
        $insertedNews = $resolveData->getInsertedNews($editorialId);

        if ($insertedNews === null) {
            throw new BodyDataTransformerNotFoundException(
                'Inserted news: editorial not found for id: ' . $editorialId,
            );
        }

        $editorial = $insertedNews->editorial;
        $section = $insertedNews->section;

        $elementArray['editorialId'] = $editorial->id()->id();
        $elementArray['title'] = $editorial->editorialTitles()->title();
        $elementArray['signatures'] = $insertedNews->signatures;
        $elementArray['editorial'] = $this->buildEditorialUrl($editorial, $section);

        $shots = $this->shotResolver
            ->resolveForInsertedEditorial($editorialId, $resolveData)
            ->toLegacyFormat();

        $elementArray['shots'] = $shots;
        $elementArray['photo'] = empty($shots) ? '' : reset($shots);

        return $elementArray;
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
