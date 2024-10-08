<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer;

use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Exceptions\BodyDataTransformerNotFoundException;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
class BodyElementDataTransformerHandler
{
    /** @var BodyElementDataTransformer[] */
    private array $dataTransformers;

    public function __construct()
    {
        $this->dataTransformers = [];
    }

    public function addDataTransformer(BodyElementDataTransformer $dataTransformer): BodyElementDataTransformerHandler
    {
        $this->dataTransformers[$dataTransformer->canTransform()] = $dataTransformer;

        return $this;
    }

    public function execute(BodyElement $bodyElement): array
    {
        if (empty($this->dataTransformers[\get_class($bodyElement)])) {
            $message = \sprintf('BodyDataTransformer type %s not found', $bodyElement->type());
            throw new BodyDataTransformerNotFoundException($message);
        }

        $transformer = $this->dataTransformers[\get_class($bodyElement)];
        $transformer->write($bodyElement);

        return $transformer->read();
    }
}
