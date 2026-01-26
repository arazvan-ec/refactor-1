<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DTO\Response;

use App\Application\DTO\Response\MultimediaResponseDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultimediaResponseDTO::class)]
class MultimediaResponseDTOTest extends TestCase
{
    #[Test]
    public function it_creates_photo_multimedia(): void
    {
        $dto = MultimediaResponseDTO::createPhoto(
            id: 'photo-1',
            caption: 'A beautiful photo',
            shots: ['16:9' => ['600w' => 'http://example.com/600w.jpg']],
            photo: 'http://example.com/photo.jpg',
        );

        self::assertSame('photo-1', $dto->id);
        self::assertSame(MultimediaResponseDTO::TYPE_PHOTO, $dto->type);
        self::assertSame('A beautiful photo', $dto->caption);
        self::assertNotEmpty($dto->shots);
        self::assertSame('http://example.com/photo.jpg', $dto->photo);
        self::assertTrue($dto->isPhoto());
        self::assertFalse($dto->isVideo());
        self::assertFalse($dto->isWidget());
    }

    #[Test]
    public function it_creates_embed_video_multimedia(): void
    {
        $dto = MultimediaResponseDTO::createEmbedVideo(
            id: 'video-1',
            caption: 'A great video',
            html: '<iframe src="..."></iframe>',
        );

        self::assertSame('video-1', $dto->id);
        self::assertSame(MultimediaResponseDTO::TYPE_EMBED_VIDEO, $dto->type);
        self::assertSame('A great video', $dto->caption);
        self::assertSame('<iframe src="..."></iframe>', $dto->html);
        self::assertFalse($dto->isPhoto());
        self::assertTrue($dto->isVideo());
        self::assertFalse($dto->isWidget());
    }

    #[Test]
    public function it_creates_dailymotion_video_multimedia(): void
    {
        $dto = MultimediaResponseDTO::createDailyMotionVideo(
            id: 'video-2',
            caption: 'DailyMotion video',
            playerId: 'player123',
            videoId: 'video456',
        );

        self::assertSame('video-2', $dto->id);
        self::assertSame(MultimediaResponseDTO::TYPE_EMBED_VIDEO_DAILYMOTION, $dto->type);
        self::assertSame('player123', $dto->playerId);
        self::assertSame('video456', $dto->videoId);
        self::assertTrue($dto->isVideo());
    }

    #[Test]
    public function it_creates_widget_multimedia(): void
    {
        $dto = MultimediaResponseDTO::createWidget(
            caption: 'Interactive widget',
            url: 'http://example.com/widget',
            aspectRatio: 1.5,
            typeSpecificData: ['customField' => 'value'],
        );

        self::assertSame(MultimediaResponseDTO::TYPE_WIDGET, $dto->type);
        self::assertSame('Interactive widget', $dto->caption);
        self::assertSame('http://example.com/widget', $dto->url);
        self::assertSame(1.5, $dto->aspectRatio);
        self::assertSame(['customField' => 'value'], $dto->typeSpecificData);
        self::assertTrue($dto->isWidget());
    }

    #[Test]
    public function it_creates_from_photo_array(): void
    {
        $data = [
            'id' => 'photo-1',
            'type' => 'photo',
            'caption' => 'Photo caption',
            'shots' => ['16:9' => ['600w' => 'http://example.com/600w.jpg']],
            'photo' => 'http://example.com/photo.jpg',
        ];

        $dto = MultimediaResponseDTO::fromArray($data);

        self::assertSame('photo-1', $dto->id);
        self::assertSame('photo', $dto->type);
        self::assertTrue($dto->isPhoto());
    }

    #[Test]
    public function it_creates_from_embed_video_array(): void
    {
        $data = [
            'id' => 'video-1',
            'type' => 'embedVideo',
            'caption' => 'Video caption',
            'html' => '<iframe></iframe>',
        ];

        $dto = MultimediaResponseDTO::fromArray($data);

        self::assertSame('video-1', $dto->id);
        self::assertSame('embedVideo', $dto->type);
        self::assertTrue($dto->isVideo());
    }

    #[Test]
    public function it_creates_from_dailymotion_array(): void
    {
        $data = [
            'id' => 'video-2',
            'type' => 'embedVideoDailyMotion',
            'caption' => 'DM video',
            'playerId' => 'p123',
            'videoId' => 'v456',
        ];

        $dto = MultimediaResponseDTO::fromArray($data);

        self::assertSame('embedVideoDailyMotion', $dto->type);
        self::assertSame('p123', $dto->playerId);
        self::assertSame('v456', $dto->videoId);
    }

    #[Test]
    public function it_creates_from_widget_array(): void
    {
        $data = [
            'type' => 'widget',
            'caption' => 'Widget',
            'url' => 'http://example.com',
            'aspectRatio' => 1.5,
            'extraField' => 'extra',
        ];

        $dto = MultimediaResponseDTO::fromArray($data);

        self::assertSame('widget', $dto->type);
        self::assertSame('http://example.com', $dto->url);
        self::assertSame(1.5, $dto->aspectRatio);
    }

    #[Test]
    public function it_converts_photo_to_array(): void
    {
        $dto = MultimediaResponseDTO::createPhoto(
            id: 'photo-1',
            caption: 'Caption',
            shots: ['16:9' => ['600w' => 'url']],
            photo: 'http://photo.jpg',
        );

        $array = $dto->toArray();

        self::assertSame('photo-1', $array['id']);
        self::assertSame('photo', $array['type']);
        self::assertSame('Caption', $array['caption']);
        self::assertArrayHasKey('shots', $array);
        self::assertSame('http://photo.jpg', $array['photo']);
    }

    #[Test]
    public function it_converts_embed_video_to_array(): void
    {
        $dto = MultimediaResponseDTO::createEmbedVideo(
            id: 'video-1',
            caption: 'Caption',
            html: '<iframe></iframe>',
        );

        $array = $dto->toArray();

        self::assertSame('video-1', $array['id']);
        self::assertSame('embedVideo', $array['type']);
        self::assertSame('<iframe></iframe>', $array['html']);
    }

    #[Test]
    public function it_converts_widget_to_array(): void
    {
        $dto = MultimediaResponseDTO::createWidget(
            caption: 'Widget',
            url: 'http://example.com',
            aspectRatio: 1.5,
        );

        $array = $dto->toArray();

        self::assertSame('widget', $array['type']);
        self::assertSame('Widget', $array['caption']);
        self::assertSame('http://example.com', $array['url']);
        self::assertSame(1.5, $array['aspectRatio']);
    }

    #[Test]
    public function widget_to_array_filters_null_values(): void
    {
        $dto = MultimediaResponseDTO::createWidget(
            caption: 'Widget',
            url: null,
            aspectRatio: null,
        );

        $array = $dto->toArray();

        self::assertArrayNotHasKey('url', $array);
        self::assertArrayNotHasKey('aspectRatio', $array);
    }
}
