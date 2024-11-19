<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Body;

use App\Infrastructure\Trait\UrlGeneratorTrait;
use Assert\Assertion;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Encode\Encode;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;

/**
 * @author Jose Guillermo Moreu Peso <jgmoreu@ext.elconfidencial.com>
 */
class BodyTagInsertedNewsDataTransformer extends ElementTypeDataTransformer
{
    use UrlGeneratorTrait;

    public function __construct(
        private readonly QueryEditorialClient $queryEditorialClient,
        private readonly QuerySectionClient $querySectionClient,
        string $extension,
    ) {
        $this->setExtension($extension);
    }

    public function canTransform(): string
    {
        return BodyTagInsertedNews::class;
    }

    public function read(): array
    {
        $message = 'BodyElement should be instance of '.BodyTagInsertedNews::class;
        Assertion::isInstanceOf($this->bodyElement, BodyTagInsertedNews::class, $message);

        $elementArray = parent::read();

        /** @var Editorial $editorial */
        $editorial = $this->queryEditorialClient->findEditorialById($this->bodyElement->editorialId()->id());
        $section = $this->querySectionClient->findSectionById($editorial->sectionId());

        $elementArray['editorialId'] = $editorial->id()->id();
        $elementArray['title'] = $editorial->editorialTitles()->title();
        $elementArray['signatures'] = $this->retrieveJournalists($editorial, $this->resolveData()['signatures']);
        $elementArray['editorial'] = $this->editorialUrl($editorial, $section);
        $elementArray['photo'] = [];

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

    private function retrieveJournalists(Editorial $editorial, array $journalists): array
    {
        $result = [];

        /** @var Signature $signature */
        foreach ($editorial->signatures()->getArrayCopy() as $signature) {
            $result[] = $this->getJournalistByAliasId($signature->id()->id(), $journalists);
        }

        return $result;
    }

    private function getJournalistByAliasId(string $aliasId, array $journalists): array
    {
        foreach ($journalists as $journalist) {
            if ($journalist['aliasId'] === $aliasId) {
                return $journalist;
            }
        }

        return [];
    }
}
