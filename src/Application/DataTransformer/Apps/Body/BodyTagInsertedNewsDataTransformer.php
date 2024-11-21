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
use Ec\Multimedia\Domain\Model\Clipping;
use Ec\Multimedia\Domain\Model\ClippingTypes;
use Ec\Multimedia\Domain\Model\Multimedia;
use Ec\Section\Domain\Model\Section;

/**
 * @author Jose Guillermo Moreu Peso <jgmoreu@ext.elconfidencial.com>
 */
class BodyTagInsertedNewsDataTransformer extends ElementTypeDataTransformer
{
    use UrlGeneratorTrait;
    use MultimediaTrait;

    /** @var string */
    private const WIDTH = 'width';

    /** @var string */
    private const HEIGHT = 'height';
    private const ASPECT_RATIO_4_3 = [
        '202w' => [
            self::WIDTH => '202',
            self::HEIGHT => '152',
        ],
        '144w' => [
            self::WIDTH => '144',
            self::HEIGHT => '108',
        ],
        '128w' => [
            self::WIDTH => '128',
            self::HEIGHT => '96',
        ],
    ];

    public function __construct(
        private readonly Thumbor $thumbor,
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

        $editorialId = $bodyElement->editorialId()->id();

        $signatures = $this->resolveData()['insertedNews'][$editorialId]['signatures'];

        /** @var Editorial $editorial */
        $editorial = $this->resolveData()['insertedNews'][$editorialId]['editorial'];
        /** @var Section $section */
        $sectionInserted = $this->resolveData()['insertedNews'][$editorialId]['section'];

        $elementArray['editorialId'] = $editorial->id()->id();
        $elementArray['title'] = $editorial->editorialTitles()->title();
        $elementArray['signatures'] = $signatures;
        $elementArray['editorial'] =  $this->editorialUrl($editorial, $sectionInserted);

        $multimedia = $this->resolveData()['multimedia'][$this->resolveData()['insertedNews'][$editorialId]['multimediaId']];
        $elementArray['photo'] = $this->getMultimedia($multimedia);

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

    public function getMultimedia(Multimedia $multimedia): array
    {
        $clippings = $multimedia->clippings();

        /** @var Clipping $clipping */
        $clipping = $clippings->clippingByType(ClippingTypes::SIZE_ARTICLE_4_3);

        $shots = [];
        $sizes = self::ASPECT_RATIO_4_3;
        foreach ($sizes as $type => $size) {
            $shots[$type] = $this->thumbor->retriveCropBodyTagPicture(
                $multimedia->file(),
                $size[self::WIDTH],
                $size[self::HEIGHT],
                $clipping->topLeftX(),
                $clipping->topLeftY(),
                $clipping->bottomRightX(),
                $clipping->bottomRightY()
            );
        }

        return [
            'id' => $multimedia->id(),
            'type' => 'photo',
            'caption' => $multimedia->caption(),
            'shots' => $shots,
            'photo' => empty($shots) ? '' : reset($shots),
        ];
    }
}
