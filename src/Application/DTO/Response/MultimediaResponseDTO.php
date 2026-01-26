<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * Base DTO for multimedia response.
 *
 * Provides type-safe structure for multimedia data.
 * Supports photos, videos, and widgets via discriminated union pattern.
 */
final readonly class MultimediaResponseDTO
{
    public const TYPE_PHOTO = 'photo';
    public const TYPE_EMBED_VIDEO = 'embedVideo';
    public const TYPE_EMBED_VIDEO_DAILYMOTION = 'embedVideoDailyMotion';
    public const TYPE_WIDGET = 'widget';

    /**
     * @param array<string, mixed> $shots Responsive image URLs (for photos)
     * @param array<string, mixed> $typeSpecificData Additional type-specific data
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $caption,
        public array $shots = [],
        public ?string $photo = null,
        public ?string $html = null,
        public ?string $playerId = null,
        public ?string $videoId = null,
        public ?string $url = null,
        public ?float $aspectRatio = null,
        public array $typeSpecificData = [],
    ) {
    }

    /**
     * Create a photo multimedia DTO.
     *
     * @param array<string, mixed> $shots
     */
    public static function createPhoto(
        string $id,
        string $caption,
        array $shots,
        string $photo,
    ): self {
        return new self(
            id: $id,
            type: self::TYPE_PHOTO,
            caption: $caption,
            shots: $shots,
            photo: $photo,
        );
    }

    /**
     * Create an embed video multimedia DTO.
     */
    public static function createEmbedVideo(
        string $id,
        string $caption,
        string $html,
    ): self {
        return new self(
            id: $id,
            type: self::TYPE_EMBED_VIDEO,
            caption: $caption,
            html: $html,
        );
    }

    /**
     * Create a DailyMotion video multimedia DTO.
     */
    public static function createDailyMotionVideo(
        string $id,
        string $caption,
        string $playerId,
        string $videoId,
    ): self {
        return new self(
            id: $id,
            type: self::TYPE_EMBED_VIDEO_DAILYMOTION,
            caption: $caption,
            playerId: $playerId,
            videoId: $videoId,
        );
    }

    /**
     * Create a widget multimedia DTO.
     *
     * @param array<string, mixed> $typeSpecificData
     */
    public static function createWidget(
        string $caption,
        ?string $url = null,
        ?float $aspectRatio = null,
        array $typeSpecificData = [],
    ): self {
        return new self(
            id: '',
            type: self::TYPE_WIDGET,
            caption: $caption,
            url: $url,
            aspectRatio: $aspectRatio,
            typeSpecificData: $typeSpecificData,
        );
    }

    /**
     * Create from legacy array response.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? '';

        return match ($type) {
            self::TYPE_PHOTO => self::createPhoto(
                id: $data['id'] ?? '',
                caption: $data['caption'] ?? '',
                shots: $data['shots'] ?? [],
                photo: $data['photo'] ?? '',
            ),
            self::TYPE_EMBED_VIDEO => self::createEmbedVideo(
                id: $data['id'] ?? '',
                caption: $data['caption'] ?? '',
                html: $data['html'] ?? '',
            ),
            self::TYPE_EMBED_VIDEO_DAILYMOTION => self::createDailyMotionVideo(
                id: $data['id'] ?? '',
                caption: $data['caption'] ?? '',
                playerId: $data['playerId'] ?? '',
                videoId: $data['videoId'] ?? '',
            ),
            default => self::createWidget(
                caption: $data['caption'] ?? '',
                url: $data['url'] ?? null,
                aspectRatio: isset($data['aspectRatio']) ? (float) $data['aspectRatio'] : null,
                typeSpecificData: array_diff_key($data, array_flip(['type', 'caption', 'url', 'aspectRatio'])),
            ),
        };
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = match ($this->type) {
            self::TYPE_PHOTO => [
                'id' => $this->id,
                'type' => $this->type,
                'caption' => $this->caption,
                'shots' => $this->shots,
                'photo' => $this->photo,
            ],
            self::TYPE_EMBED_VIDEO => [
                'id' => $this->id,
                'type' => $this->type,
                'caption' => $this->caption,
                'html' => $this->html,
            ],
            self::TYPE_EMBED_VIDEO_DAILYMOTION => [
                'id' => $this->id,
                'type' => $this->type,
                'caption' => $this->caption,
                'playerId' => $this->playerId,
                'videoId' => $this->videoId,
            ],
            default => array_merge([
                'type' => $this->type,
                'caption' => $this->caption,
                'url' => $this->url,
                'aspectRatio' => $this->aspectRatio,
            ], $this->typeSpecificData),
        };

        return array_filter($result, static fn ($value) => null !== $value);
    }

    public function isPhoto(): bool
    {
        return self::TYPE_PHOTO === $this->type;
    }

    public function isVideo(): bool
    {
        return \in_array($this->type, [self::TYPE_EMBED_VIDEO, self::TYPE_EMBED_VIDEO_DAILYMOTION], true);
    }

    public function isWidget(): bool
    {
        return self::TYPE_WIDGET === $this->type;
    }
}
