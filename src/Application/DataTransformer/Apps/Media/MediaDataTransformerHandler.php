<?php

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Media;

use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\Opening;
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
     * @param array<string, mixed> $multimediaOpeningData
     *
     * @return array<string, mixed>
     *
     * @throws MultimediaDataTransformerNotFoundException
     */
    public function execute(array $multimediaOpeningData, Opening $openingData): array
    {
        $multimediaElement = $multimediaOpeningData[$openingData->multimediaId()]['opening'];
        if (empty($this->dataTransformers[\get_class($multimediaElement)])) {
            $message = \sprintf('Media data transformer type %s not found', $multimediaElement->type());
            throw new MultimediaDataTransformerNotFoundException($message);
        }

        $transformer = $this->dataTransformers[\get_class($multimediaElement)];

        return $transformer->write($multimediaOpeningData, $openingData)->read();
    }
}
