<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\Trait\LinksDataTransformer;
use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Domain\Model\Body\ElementContentWithLinks;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
abstract class ElementContentWithLinksDataTransformer extends ElementContentDataTransformer
{
    use LinksDataTransformer;

    /** @var ElementContentWithLinks */
    protected BodyElement $bodyElement;

    public function read(): array
    {
        $elementArray = parent::read();
        $elementArray['links'] = $this->readLinks($this->bodyElement);

        return $elementArray;
    }
}
