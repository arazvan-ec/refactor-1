<?php

namespace App\Infrastructure\Service;


use Ec\Editorial\Domain\Model\Body\BodyTagPictureDefault;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class PictureShots
{

    private const WIDTH = 'width';

    /** @var string */
    private const HEIGHT = 'height';

    private const ASPECT_RATIO_16_9 = '16:9';

    /** @var string */
    private const ASPECT_RATIO_3_4 = '3:4';

    /** @var string */
    private const ASPECT_RATIO_4_3 = '4:3';

    /** @var string */
    private const ASPECT_RATIO_1_1 = '1:1';

    public const SIZES_RELATIONS = [
        self::ASPECT_RATIO_16_9 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '810'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '675'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '560'],
            '640w' => [self::WIDTH => '640', self::HEIGHT => '360'],
            '390w' => [self::WIDTH => '390', self::HEIGHT => '219'],
            '568w' => [self::WIDTH => '568', self::HEIGHT => '320'],
            '382w' => [self::WIDTH => '382', self::HEIGHT => '215'],
            '328w' => [self::WIDTH => '328', self::HEIGHT => '185'],
        ],
        self::ASPECT_RATIO_3_4 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '1920'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '1600'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '1328'],
            '560w' => [self::WIDTH => '560', self::HEIGHT => '747'],
            '390w' => [self::WIDTH => '390', self::HEIGHT => '520'],
            '568w' => [self::WIDTH => '568', self::HEIGHT => '757'],
            '382w' => [self::WIDTH => '382', self::HEIGHT => '509'],
            '328w' => [self::WIDTH => '328', self::HEIGHT => '437'],
        ],
        self::ASPECT_RATIO_1_1 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '1440'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '1200'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '996'],
            '560w' => [self::WIDTH => '560', self::HEIGHT => '560'],
            '390w' => [self::WIDTH => '390', self::HEIGHT => '390'],
            '568w' => [self::WIDTH => '568', self::HEIGHT => '568'],
            '382w' => [self::WIDTH => '382', self::HEIGHT => '382'],
            '328w' => [self::WIDTH => '328', self::HEIGHT => '328'],
        ],
        self::ASPECT_RATIO_4_3 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '1920'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '1600'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '1328'],
            '560w' => [self::WIDTH => '560', self::HEIGHT => '747'],
            '390w' => [self::WIDTH => '390', self::HEIGHT => '520'],
            '568w' => [self::WIDTH => '568', self::HEIGHT => '757'],
            '382w' => [self::WIDTH => '382', self::HEIGHT => '509'],
            '328w' => [self::WIDTH => '328', self::HEIGHT => '437'],
        ],
    ];

    public function __construct(
        private Thumbor $thumbor
    )
    {
    }


    private function retriveAspectRatio(string $topX, string $topY, string $bottomX, string $bottomY): string
    {

        $width = $bottomX - $topX;
        $height = $bottomY - $topY;
        $aspectRatio = $width / $height;
        $result = self::ASPECT_RATIO_16_9;

        if (1 === $aspectRatio) {
            $result = self::ASPECT_RATIO_1_1;
        } elseif ($aspectRatio < 1) {
            $result = self::ASPECT_RATIO_3_4;
        } elseif ($aspectRatio > 1 && $aspectRatio < 1.3) {
            $result = self::ASPECT_RATIO_4_3;
        }

        return $result;
    }

    private function retriveAllShotsByAspectRatio(string $fileName, BodyTagPictureDefault $bodytag): array
    {
        $shots = [];
        $aspectRatio = $this->retriveAspectRatio(
            $bodytag->topX(),
            $bodytag->topY(),
            $bodytag->bottomX(),
            $bodytag->bottomY()
        );
        foreach (self::SIZES_RELATIONS[$aspectRatio] as $viewport => $sizeValues) {
            $shots[$viewport] = $this->thumbor->retriveCropBodyTagPicture(
                $fileName,
                $sizeValues[self::WIDTH],
                $sizeValues[self::HEIGHT],
                $bodytag->topX(),
                $bodytag->topY(),
                $bodytag->bottomX(),
                $bodytag->bottomY()
            );
        }

        return $shots;
    }

    public function retrieveShotsByPhotoId(array $resolveData, BodyTagPictureDefault $bodyTagPicture) : array
    {
        $photoFile=$this->retrievePhotoFile($resolveData,$bodyTagPicture);
        if ($photoFile){
            return $this->retriveAllShotsByAspectRatio($photoFile,$bodyTagPicture);
        }
        return [];
    }

    private function retrievePhotoFile(array $resolveData,BodyTagPictureDefault $bodyTagPicture) : string
    {
        $photoFile='';

        if (!isset($resolveData['photoFromBodyTags'])){
            return $photoFile;
        }
        $photoFromBodyTags=$resolveData['photoFromBodyTags'];

        if (isset($photoFromBodyTags[$bodyTagPicture->id()->id()])){
            return $photoFromBodyTags[$bodyTagPicture->id()->id()]->file();
        }
        return $photoFile;

    }


}
