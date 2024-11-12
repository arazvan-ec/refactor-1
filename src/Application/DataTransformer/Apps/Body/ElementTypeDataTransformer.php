<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\BodyElementDataTransformer;
use Ec\Editorial\Domain\Model\Body\BodyElement;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
abstract class ElementTypeDataTransformer implements BodyElementDataTransformer
{
    protected BodyElement $bodyElement;
    private array $resolveData;
    private array $membershipLinkCombine;

    public function write(
        BodyElement $bodyElement,
        array $resolveData = [],
        array $membershipLinkCombine = []
    ): BodyElementDataTransformer
    {
        $this->bodyElement = $bodyElement;
        $this->resolveData = $resolveData;
        $this->membershipLinkCombine = $membershipLinkCombine;

        return $this;
    }

    public function read(): array
    {
        $elementArray = [];
        $elementArray['type'] = $this->bodyElement->type();

        return $elementArray;
    }

    public function resolveData() : array
    {
        return $this->resolveData;
    }

    public function membershipLinkCombine() : array
    {
        return $this->membershipLinkCombine;
    }
}
