<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Body;

use App\Infrastructure\Service\PictureShots;
use App\Infrastructure\Service\Thumbor;
use Assert\Assertion;
use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use Ec\Editorial\Domain\Model\Body\BodyTagPictureDefault;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class BodyTagPictureDataTransformer extends ElementTypeDataTransformer
{

    /** @var BodyTagPicture */
    protected BodyElement $bodyElement;

    public function __construct(
        private readonly PictureShots $pictureShots,
    ) {
    }

    public function read(): array
    {
        $message = 'BodyElement should be instance of '.BodyTagPicture::class;
        Assertion::isInstanceOf($this->bodyElement, BodyTagPicture::class, $message);

        $elementArray = parent::read();
        $shots=$this->pictureShots->retriveShotsByPhotoId($this->resolveData(),$this->bodyElement);

        if (count($shots)){
            $elementArray['shots'] = $shots;
            $elementArray['caption'] = $this->bodyElement->caption();
            $elementArray['alternate'] = $this->bodyElement->alternate();
            $elementArray['orientation'] = $this->bodyElement->orientation();
        }
        return $elementArray;
    }

    public function canTransform(): string
    {
        return BodyTagPicture::class;
    }

}
