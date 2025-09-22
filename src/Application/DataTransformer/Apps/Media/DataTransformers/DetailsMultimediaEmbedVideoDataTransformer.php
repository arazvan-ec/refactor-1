<?php

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Media\DataTransformers;

use App\Application\DataTransformer\Apps\Media\MediaDataTransformer;
use Ec\Editorial\Domain\Model\Opening;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaEmbedVideo;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class DetailsMultimediaEmbedVideoDataTransformer implements MediaDataTransformer
{
    /** @var string */
    private const EMBED_VIDEO_GENERIC = 'EmbedVideo';

    /** @var string */
    private const EMBED_VIDEO_DAILY_MOTION = 'EmbedVideoDailyMotion';

    /**
     * @var array{array{opening: MultimediaEmbedVideo}}|array{}
     */
    private array $arrayMultimedia;
    private Opening $openingMultimedia;

    /**
     * @param array{array{opening: MultimediaEmbedVideo}}|array{} $arrayMultimedia
     */
    public function write(
        array $arrayMultimedia,
        Opening $openingMultimedia,
    ): DetailsMultimediaEmbedVideoDataTransformer {
        $this->arrayMultimedia = $arrayMultimedia;
        $this->openingMultimedia = $openingMultimedia;

        return $this;
    }

    /**
     * @return array<string, \stdClass|string>
     */
    public function read(): array
    {
        $multimediaId = $this->openingMultimedia->multimediaId();

        if (!$multimediaId || empty($this->arrayMultimedia[$multimediaId])) {
            return [
                'id' => '',
                'type' => 'multimediaNull',
            ];
        }

        /** @var MultimediaEmbedVideo $multimedia */
        $multimedia = $this->arrayMultimedia[$multimediaId]['opening'];

        return $this->isDailyMotionVideo($multimedia)
            ? $this->buildDailyMotionResponse($multimediaId, $multimedia)
            : $this->buildGenericResponse($multimediaId, $multimedia);
    }

    public function canTransform(): string
    {
        return MultimediaEmbedVideo::class;
    }

    private function isDailyMotionVideo(MultimediaEmbedVideo $multimedia): bool
    {
        return str_contains($multimedia->html(), 'dailymotion.com');
    }

    /**
     * @return array{id: string, type: string, caption: string, playerDailyMotionId: string, videoDailyMotionId: string}
     */
    private function buildDailyMotionResponse(string $multimediaId, MultimediaEmbedVideo $multimedia): array
    {
        $dailyMotionData = $this->extractDailyMotionData($multimedia);

        return [
            'id' => $multimediaId,
            'type' => self::EMBED_VIDEO_DAILY_MOTION,
            'caption' => $multimedia->caption(),
            'playerDailyMotionId' => $dailyMotionData['playerId'],
            'videoDailyMotionId' => $dailyMotionData['videoId'],
        ];
    }

    /**
     * @return array{type: string, playerId: string, videoId: string}
     */
    private function buildGenericResponse(string $multimediaId, MultimediaEmbedVideo $multimedia): array
    {
        return [
            'id' => $multimediaId,
            'type' => self::EMBED_VIDEO_GENERIC,
            'caption' => $multimedia->caption(),
            'embedText' => $multimedia->html(),
        ];
    }

    /**
     * @return array{type: string, playerId: string, videoId: string}
     */
    private function extractDailyMotionData(MultimediaEmbedVideo $multimedia): array
    {
        $htmlContent = $multimedia->html();

        preg_match('/\/player\/([a-zA-Z0-9]+)\.html\?video=([a-zA-Z0-9]+)/', $htmlContent, $matches);

        return [
            'playerId' => $matches[1],
            'videoId' => $matches[2],
        ];
    }
}
