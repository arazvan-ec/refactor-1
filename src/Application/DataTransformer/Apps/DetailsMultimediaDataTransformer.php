<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps;

use App\Infrastructure\Service\Thumbor;
use Ec\Multimedia\Domain\Model\Clipping;
use Ec\Multimedia\Domain\Model\ClippingTypes;
use Ec\Multimedia\Domain\Model\Multimedia;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
class DetailsMultimediaDataTransformer implements MultimediaDataTransformer
{
    /** @var string */
    private const WIDTH = 'width';

    /** @var string */
    private const HEIGHT = 'height';

    /** @var string */
    private const ASPECT_RATIO_16_9 = '16:9';

    /** @var string */
    private const ASPECT_RATIO_3_4 = '3:4';

    /** @var string */
    private const ASPECT_RATIO_4_3 = '4:3';

    /** @var string */
    private const ASPECT_RATIO_1_1 = '1:1';

    private const SIZES_RELATIONS = [
        self::ASPECT_RATIO_4_3 => [
            // High density
            '1440w' => [
                self::WIDTH => '1440',
                self::HEIGHT => '1080',
            ],
            '1200w' => [
                self::WIDTH => '1200',
                self::HEIGHT => '900',
            ],
            '996w' => [
                self::WIDTH => '996',
                self::HEIGHT => '747',
            ],
            // Desktop
            '557w' => [
                self::WIDTH => '557',
                self::HEIGHT => '418',
            ],
            // Tablet
            '381w' => [
                self::WIDTH => '381',
                self::HEIGHT => '286',
            ],
            // Mobile
            '600w' => [
                self::WIDTH => '600',
                self::HEIGHT => '450',
            ],
            '414w' => [
                self::WIDTH => '414',
                self::HEIGHT => '311',
            ],
            '375w' => [
                self::WIDTH => '375',
                self::HEIGHT => '281',
            ],
            '360w' => [
                self::WIDTH => '360',
                self::HEIGHT => '270',
            ],
            // landscapePhotoFull
            '767w' => [
                self::WIDTH => '767',
                self::HEIGHT => '575',
            ],
        ],
        self::ASPECT_RATIO_16_9 => [
            // High density
            '1440w' => [
                self::WIDTH => '1440',
                self::HEIGHT => '810',
            ],
            '1200w' => [
                self::WIDTH => '1200',
                self::HEIGHT => '675',
            ],
            // Desktop
            '972w' => [
                self::WIDTH => '972',
                self::HEIGHT => '547',
            ],
            // Tablet
            '720w' => [
                self::WIDTH => '720',
                self::HEIGHT => '405',
            ],
            // Mobile
            '600w' => [
                self::WIDTH => '600',
                self::HEIGHT => '338',
            ],
            '414w' => [
                self::WIDTH => '414',
                self::HEIGHT => '233',
            ],
            '375w' => [
                self::WIDTH => '375',
                self::HEIGHT => '211',
            ],
            '360w' => [
                self::WIDTH => '360',
                self::HEIGHT => '203',
            ],
        ],
        self::ASPECT_RATIO_3_4 => [
            // High density
            '1440w' => [
                self::WIDTH => '1440',
                self::HEIGHT => '1920',
            ],
            '1200w' => [
                self::WIDTH => '1200',
                self::HEIGHT => '1600',
            ],
            '996w' => [
                self::WIDTH => '996',
                self::HEIGHT => '1328',
            ],
            // Desktop
            '391w' => [
                self::WIDTH => '391',
                self::HEIGHT => '521',
            ],
            // Tablet
            '300w' => [
                self::WIDTH => '300',
                self::HEIGHT => '400',
            ],
            // Mobile
            '600w' => [
                self::WIDTH => '600',
                self::HEIGHT => '800',
            ],
            '414w' => [
                self::WIDTH => '414',
                self::HEIGHT => '552',
            ],
            '375w' => [
                self::WIDTH => '375',
                self::HEIGHT => '500',
            ],
            '360w' => [
                self::WIDTH => '360',
                self::HEIGHT => '480',
            ],
        ],
    ];

    private Multimedia $multimedia;

    public function __construct(private readonly Thumbor $thumbor)
    {
    }

    public function write(Multimedia $multimedia): MultimediaDataTransformer
    {
        $this->multimedia = $multimedia;

        return $this;
    }

    public function read(): array
    {
        $clippings = $this->multimedia->clippings();

        /** @var Clipping $clipping */
        $clipping = $clippings->clippingByType(ClippingTypes::SIZE_MULTIMEDIA_BIG);

        $shots = [];
        $sizes = self::SIZES_RELATIONS[$this->retrieveAspectRatio($clipping->width(), $clipping->height())];
        foreach ($sizes as $type => $size) {
            $shots[$type] = $this->thumbor->retriveCropBodyTagPicture(
                $this->multimedia->file(),
                $size[self::WIDTH],
                $size[self::HEIGHT],
                $clipping->topLeftX(),
                $clipping->topLeftY(),
                $clipping->bottomRightX(),
                $clipping->bottomRightY()
            );
        }

        return [
            'id' => $this->multimedia->id(),
            'type' => 'photo',
            'caption' => $this->multimedia->caption(),
            'shots' => (object) $shots,
            'photo' => empty($shots) ? '' : reset($shots),
        ];
    }

    private function retrieveAspectRatio(int $width, int $height): string
    {
        $aspectRatio = $width / $height;
        $result = self::ASPECT_RATIO_16_9;

        if (1 === $aspectRatio) {
            $result = self::ASPECT_RATIO_1_1;
        } elseif ($aspectRatio < 1) {
            $result = self::ASPECT_RATIO_3_4;
        } elseif ($aspectRatio > 1 && $aspectRatio < 1.4) {
            $result = self::ASPECT_RATIO_4_3;
        }

        return $result;
    }
}
