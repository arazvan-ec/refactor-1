<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Body;

use Assert\Assertion;
use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Domain\Model\Body\BodyTagHtml;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class BodyTagMembershipCardDataTransformer extends ElementContentDataTransformer
{
    /** @var BodyTagHtml */
    protected BodyElement $bodyElement;

    public function read(): array
    {
        $message = 'BodyElement should be instance of '.BodyTagMembershipCard::class;
        Assertion::isInstanceOf($this->bodyElement, BodyTagMembershipCard::class, $message);

        return parent::read();
    }

    public function canTransform(): string
    {
        return BodyTagMembershipCard::class;
    }
}
