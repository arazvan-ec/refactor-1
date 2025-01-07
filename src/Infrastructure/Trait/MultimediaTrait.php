<?php
/**
 * @copyright
 */

namespace App\Infrastructure\Trait;

use Ec\Editorial\Domain\Model\Multimedia\Multimedia;
use Ec\Editorial\Domain\Model\Multimedia\MultimediaId;
use Ec\Editorial\Domain\Model\Multimedia\PhotoExist;
use Ec\Editorial\Domain\Model\Multimedia\Video;
use Ec\Editorial\Domain\Model\Multimedia\Widget;
use Ec\Multimedia\Domain\Model\ClippingTypes;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
trait MultimediaTrait
{
    private function getMultimediaId(Multimedia $multimedia): ?MultimediaId
    {
        $multimediaId = null;
        if ($multimedia instanceof PhotoExist) {
            $multimediaId = $multimedia->id();
        }

        if (
            ($multimedia instanceof Video || $multimedia instanceof Widget)
            && ($multimedia->photo() instanceof PhotoExist)
        ) {
            $multimediaId = $multimedia->photo()->id();
        }

        return $multimediaId;
    }

    /**
     * @return array<string, string>
     */
    private function getShotsLandscape(\Ec\Multimedia\Domain\Model\Multimedia $multimedia): array
    {
        $shots = [];

        $clippings = $multimedia->clippings();
        $clipping = $clippings->clippingByType(ClippingTypes::SIZE_ARTICLE_4_3);

        $sizes = self::ASPECT_RATIO_4_3;
        foreach ($sizes as $type => $size) {
            $shots[$type] = $this->thumbor->retriveCropBodyTagPicture(
                $multimedia->file(),
                $size[self::WIDTH],
                $size[self::HEIGHT],
                $clipping->topLeftX(),
                $clipping->topLeftY(),
                $clipping->bottomRightX(),
                $clipping->bottomRightY()
            );
        }

        return $shots;
    }
}
