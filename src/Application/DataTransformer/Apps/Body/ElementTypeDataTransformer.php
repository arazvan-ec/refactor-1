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

    public function write(BodyElement $bodyElement): BodyElementDataTransformer
    {
        $this->bodyElement = $bodyElement;

        return $this;
    }

    public function read(): array
    {
        $elementArray = [];
        $elementArray['type'] = $this->bodyElement->type();

        return $elementArray;
    }
}
