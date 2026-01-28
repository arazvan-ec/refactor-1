<?php

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps;

use App\Infrastructure\Config\MultimediaImageSizes;
use App\Infrastructure\Service\Thumbor;
use App\Infrastructure\Trait\MultimediaTrait;
use Ec\Editorial\Domain\Model\Multimedia\Multimedia as MultimediaEditorial;
use Ec\Multimedia\Domain\Model\ClippingTypes;
use Ec\Multimedia\Domain\Model\Multimedia;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
class DetailsMultimediaDataTransformer implements MultimediaDataTransformer
{
    use MultimediaTrait;

    /**
     * @var array<mixed>
     */
    private array $arrayMultimedia;
    private MultimediaEditorial $openingMultimedia;

    public function __construct(private readonly Thumbor $thumborService)
    {
    }

    /**
     * @param array<mixed> $arrayMultimedia
     */
    public function write(array $arrayMultimedia, MultimediaEditorial $openingMultimedia): MultimediaDataTransformer
    {
        $this->arrayMultimedia = $arrayMultimedia;
        $this->openingMultimedia = $openingMultimedia;

        return $this;
    }

    /**
     * @return array<string, \stdClass|string>|array{}
     */
    public function read(): array
    {
        $multimediaId = $this->getMultimediaId($this->openingMultimedia);
        if (!$multimediaId || empty($this->arrayMultimedia[$multimediaId->id()])) {
            return [];
        }
        /** @var Multimedia $multimedia */
        $multimedia = $this->arrayMultimedia[$multimediaId->id()];
        $clippings = $multimedia->clippings();

        $clipping = $clippings->clippingByType(ClippingTypes::SIZE_MULTIMEDIA_BIG);

        $allShots = [];
        foreach (MultimediaImageSizes::SIZES_RELATIONS as $aspectRatio => $sizes) {
            $shots = array_map(function ($size) use ($clipping, $multimedia) {
                return $this->thumborService->retriveCropBodyTagPicture(
                    $multimedia->file(),
                    $size[MultimediaImageSizes::WIDTH],
                    $size[MultimediaImageSizes::HEIGHT],
                    $clipping->topLeftX(),
                    $clipping->topLeftY(),
                    $clipping->bottomRightX(),
                    $clipping->bottomRightY()
                );
            }, $sizes);

            $allShots[$aspectRatio] = $shots;
        }

        return [
            'id' => $multimedia->id(),
            'type' => 'photo',
            'caption' => $multimedia->caption(),
            'shots' => (object) $allShots,
            'photo' => current($allShots[MultimediaImageSizes::ASPECT_RATIO_16_9]),
        ];
    }
}
