<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Body;

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
        /** @var BodyTagInsertedNews $bodyElement */
        $bodyElement = $this->bodyElement;
        Assertion::isInstanceOf($bodyElement, BodyTagInsertedNews::class, $message);

        $elementArray = parent::read();

        $signatures = $this->resolveData()['insertedNews'][$bodyElement->editorialId()->id()]['signatures'];

        /** @var Editorial $editorial */
        $editorial = $this->resolveData()['insertedNews'][$bodyElement->editorialId()->id()]['editorial'];
        /** @var Section $section */
        $section = $this->resolveData()['insertedNews'][$bodyElement->editorialId()->id()]['section'];

        $elementArray['editorialId'] = $editorial->id()->id();
        $elementArray['title'] = $editorial->editorialTitles()->title();
        $elementArray['signatures'] = $this->retrieveJournalists($signatures, $this->resolveData()['signatures']);
        $elementArray['editorial'] =  $this->editorialUrl($editorial, $section);

        $elementArray['photo'] = '';
        $multimediaId = $this->getMultimediaId($editorial->multimedia());
        if ($multimediaId && !empty($this->resolveData()['multimedia'][$multimediaId->id()])) {
            $elementArray['photo'] = $this->resolveData()['multimedia'][$multimediaId->id()]->file();
        }

        return $elementArray;
    }

    private function retrieveJournalists(array $journalistsInserted, array $journalists): array
    {
        $result = [];

        foreach ($journalistsInserted as $signature) {
            $result[] = $journalists[$signature];
        }

        return $result;
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
}
