<?php

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Media;

use Ec\Editorial\Exceptions\MultimediaDataTransformerNotFoundException;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class MediaDataTransformerHandler
{
    /** @var MediaDataTransformer[] */
    private array $dataTransformers;

    public function __construct()
    {
        $this->dataTransformers = [];
    }

    public function addDataTransformer(MediaDataTransformer $dataTransformer): MediaDataTransformerHandler
    {
        $this->dataTransformers[$dataTransformer->canTransform()] = $dataTransformer;

        return $this;
    }

    /**
     * @param array<string, mixed> $resolveData
     *
     * @return array<string, mixed>
     *
     * @throws MultimediaDataTransformerNotFoundException
     */
    public function execute(Multimedia $multimediaElement, array $resolveData = []): array
    {
        if (empty($this->dataTransformers[\get_class($multimediaElement)])) {
            $message = \sprintf('Media data transformer type %s not found', $multimediaElement->type());
            throw new MultimediaDataTransformerNotFoundException($message);
        }

        $transformer = $this->dataTransformers[\get_class($multimediaElement)];

        return $transformer->write($multimediaElement, $resolveData)->read();
    }
}
