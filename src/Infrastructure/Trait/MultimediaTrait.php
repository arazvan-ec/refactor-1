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
}
