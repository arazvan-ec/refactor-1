<?php

namespace App\Infrastructure\Service;

use App\Infrastructure\Config\MultimediaImageSizes;
use Ec\Editorial\Domain\Model\Body\AbstractPicture;
use Ec\Editorial\Domain\Model\Body\BodyTagPictureDefault;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class PictureShots
{
    public function __construct(
        private Thumbor $thumbor,
    ) {
    }

    private function retrieveAspectRatio(string $orientation): string
    {
        $result = MultimediaImageSizes::ASPECT_RATIO_16_9;

        if (AbstractPicture::ORIENTATION_SQUARE === $orientation) {
            $result = MultimediaImageSizes::ASPECT_RATIO_1_1;
        } elseif (AbstractPicture::ORIENTATION_PORTRAIT === $orientation) {
            $result = MultimediaImageSizes::ASPECT_RATIO_3_4;
        } elseif (AbstractPicture::ORIENTATION_LANDSCAPE === $orientation) {
            $result = MultimediaImageSizes::ASPECT_RATIO_4_3;
        } elseif (AbstractPicture::ORIENTATION_LANDSCAPE_3_2 === $orientation) {
            $result = MultimediaImageSizes::ASPECT_RATIO_3_2;
        } elseif (AbstractPicture::ORIENTATION_PORTRAIT_2_3 === $orientation) {
            $result = MultimediaImageSizes::ASPECT_RATIO_2_3;
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function retrieveAllShotsByAspectRatio(string $fileName, BodyTagPictureDefault $bodytag): array
    {
        $shots = [];
        $aspectRatio = $this->retrieveAspectRatio($bodytag->orientation());
        foreach (MultimediaImageSizes::BODY_TAG_SIZES_RELATIONS[$aspectRatio] as $viewport => $sizeValues) {
            $shots[$viewport] = $this->thumbor->retriveCropBodyTagPicture(
                $fileName,
                $sizeValues[MultimediaImageSizes::WIDTH],
                $sizeValues[MultimediaImageSizes::HEIGHT],
                $bodytag->topX(),
                $bodytag->topY(),
                $bodytag->bottomX(),
                $bodytag->bottomY()
            );
        }

        return $shots;
    }

    /**
     * @param array<string, mixed> $resolveData
     *
     * @return array|string[]
     */
    public function retrieveShotsByPhotoId(array $resolveData, BodyTagPictureDefault $bodyTagPicture): array
    {
        $photoFile = $this->retrievePhotoFile($resolveData, $bodyTagPicture);
        if ($photoFile) {
            return $this->retrieveAllShotsByAspectRatio($photoFile, $bodyTagPicture);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $resolveData
     */
    private function retrievePhotoFile(array $resolveData, BodyTagPictureDefault $bodyTagPicture): string
    {
        $photoFile = '';

        if (!isset($resolveData['photoFromBodyTags'])) {
            return $photoFile;
        }
        /** @var array<string, string> $photoFromBodyTags */
        $photoFromBodyTags = $resolveData['photoFromBodyTags'];

        if (isset($photoFromBodyTags[$bodyTagPicture->id()->id()])) {
            return $photoFromBodyTags[$bodyTagPicture->id()->id()]->file(); // @phpstan-ignore method.nonObject
        }

        return $photoFile;
    }
}
