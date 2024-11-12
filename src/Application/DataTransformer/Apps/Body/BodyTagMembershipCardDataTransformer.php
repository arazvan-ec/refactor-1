<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\BodyElementDataTransformerHandler;
use App\Infrastructure\Service\PictureShots;
use Assert\Assertion;
use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Domain\Model\Body\BodyTagHtml;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\BodyTagPictureDefault;


/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class BodyTagMembershipCardDataTransformer extends ElementTypeDataTransformer
{
    /** @var BodyTagHtml */
    protected BodyElement $bodyElement;


    public function __construct(
       private readonly BodyElementDataTransformerHandler $bodyElementDataTransformerHandler,
    )
    {

    }


    public function read(): array
    {
        $message = 'BodyElement should be instance of '.BodyTagMembershipCard::class;
        Assertion::isInstanceOf($this->bodyElement, BodyTagMembershipCard::class, $message);

        $elementArray = parent::read();
        $elementArray['picture'] = $this->bodyElementDataTransformerHandler->execute(
            $this->bodyElement->bodyTagPictureMembership(),$this->resolveData()
        );

        return $elementArray;
    }

    public function canTransform(): string
    {
        return BodyTagMembershipCard::class;
    }


}
