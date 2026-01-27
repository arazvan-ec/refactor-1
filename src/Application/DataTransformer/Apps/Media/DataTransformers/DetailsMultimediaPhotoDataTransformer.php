<?php

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Media\DataTransformers;

use App\Application\DataTransformer\Apps\Media\MediaDataTransformer;
use App\Infrastructure\Config\MultimediaImageSizes;
use App\Infrastructure\Service\Thumbor;
use App\Infrastructure\Trait\MultimediaTrait;
use Ec\Editorial\Domain\Model\Opening;
use Ec\Multimedia\Domain\Model\ClippingTypes;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Domain\Model\Photo\Photo;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
class DetailsMultimediaPhotoDataTransformer implements MediaDataTransformer
{
    use MultimediaTrait;

    /**
     * @var array{array{opening: MultimediaPhoto, resource: Photo}}|array{}
     */
    private array $arrayMultimedia;
    private Opening $openingMultimedia;

    public function __construct(private readonly Thumbor $thumborService)
    {
    }

    /**
     * @param array{array{opening: MultimediaPhoto, resource: Photo}}|array{} $arrayMultimedia
     */
    public function write(array $arrayMultimedia, Opening $openingMultimedia): self
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
        $multimediaId = $this->openingMultimedia->multimediaId();

        if (!$multimediaId || empty($this->arrayMultimedia[$multimediaId])) {
            return [];
        }

        /** @var MultimediaPhoto $multimedia */
        $multimedia = $this->arrayMultimedia[$multimediaId]['opening'];
        /** @var Photo $resource */
        $resource = $this->arrayMultimedia[$multimediaId]['resource'];
        $clippings = $multimedia->clippings();

        $clipping = $clippings->clippingByType(ClippingTypes::SIZE_MULTIMEDIA_BIG);

        $allShots = [];
        foreach (MultimediaImageSizes::SIZES_RELATIONS as $aspectRatio => $sizes) {
            $shots = array_map(function ($size) use ($clipping, $resource) {
                return $this->thumborService->retriveCropBodyTagPicture(
                    $resource->file(),
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
            'id' => $multimediaId,
            'type' => 'photo',
            'caption' => $multimedia->caption(),
            'shots' => (object) $allShots,
            'photo' => current($allShots[MultimediaImageSizes::ASPECT_RATIO_16_9]),
        ];
    }

    public function canTransform(): string
    {
        return MultimediaPhoto::class;
    }
}
