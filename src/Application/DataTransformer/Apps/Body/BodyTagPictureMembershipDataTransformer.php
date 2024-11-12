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
use Ec\Editorial\Domain\Model\Body\BodyTagPictureMembership;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class BodyTagPictureMembershipDataTransformer extends ElementTypeDataTransformer
{

    /** @var BodyTagPicture */
    protected BodyElement $bodyElement;

    public function __construct(
        private readonly PictureShots $pictureShots,
    ) {
    }

    public function read(): array
    {
        $message = 'BodyElement should be instance of '.BodyTagPictureMembership::class;
        Assertion::isInstanceOf($this->bodyElement, BodyTagPictureMembership::class, $message);

        $elementArray = parent::read();
        $file=$this->retrievePhotoFile($this->bodyElement);
        if ($file){
            $elementArray['shots'] = $this->pictureShots->retriveAllShotsByAspectRatio($file, $this->bodyElement);
        }
        $elementArray['caption'] = $this->bodyElement->caption();
        $elementArray['alternate'] = $this->bodyElement->alternate();
        $elementArray['orientation'] = $this->bodyElement->orientation();

        return $elementArray;
    }

    public function canTransform(): string
    {
        return BodyTagPicture::class;
    }

    private function retrievePhotoFile(BodyTagPictureDefault $bodyTagPicture) : string
    {
        $photoFile='';
        $resolveData=$this->resolveData();
        if (!isset($resolveData['photoFromBodyTags'])){
            return $photoFile;
        }
        $photoFromBodyTags=$resolveData['photoFromBodyTags'];

        if (isset($photoFromBodyTags[$bodyTagPicture->id()->id()])){
            return $photoFromBodyTags[$bodyTagPicture->id()->id()]->file();
        }
        return $photoFile;

    }


}
