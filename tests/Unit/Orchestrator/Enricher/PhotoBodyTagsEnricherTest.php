<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orchestrator\Enricher;

use App\Application\DTO\BatchResult;
use App\Application\DTO\EmbeddedContentDTO;
use App\Application\Service\Promise\PromiseResolverInterface;
use App\Orchestrator\DTO\EditorialContext;
use App\Orchestrator\Enricher\ContentEnricherInterface;
use App\Orchestrator\Enricher\PhotoBodyTagsEnricher;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use Ec\Editorial\Domain\Model\Body\BodyTagPictureMembership;
use Ec\Editorial\Domain\Model\Body\BodyTagPictureId;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Multimedia\Domain\Model\Photo;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\Section;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PhotoBodyTagsEnricher::class)]
class PhotoBodyTagsEnricherTest extends TestCase
{
    private PhotoBodyTagsEnricher $enricher;
    private MockObject&QueryMultimediaClient $queryMultimediaClient;
    private MockObject&PromiseResolverInterface $promiseResolver;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->queryMultimediaClient = $this->createMock(QueryMultimediaClient::class);
        $this->promiseResolver = $this->createMock(PromiseResolverInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->enricher = new PhotoBodyTagsEnricher(
            $this->queryMultimediaClient,
            $this->promiseResolver,
            $this->logger
        );
    }

    #[Test]
    public function it_implements_content_enricher_interface(): void
    {
        self::assertInstanceOf(ContentEnricherInterface::class, $this->enricher);
    }

    #[Test]
    public function it_has_priority_80(): void
    {
        self::assertSame(80, $this->enricher->getPriority());
    }

    #[Test]
    public function it_supports_editorials_with_body_tag_pictures(): void
    {
        $editorial = $this->createEditorialWithBodyElements(hasPictures: true, hasMembershipCards: false);

        self::assertTrue($this->enricher->supports($editorial));
    }

    #[Test]
    public function it_supports_editorials_with_membership_cards(): void
    {
        $editorial = $this->createEditorialWithBodyElements(hasPictures: false, hasMembershipCards: true);

        self::assertTrue($this->enricher->supports($editorial));
    }

    #[Test]
    public function it_does_not_support_editorials_without_photo_elements(): void
    {
        $editorial = $this->createEditorialWithBodyElements(hasPictures: false, hasMembershipCards: false);

        self::assertFalse($this->enricher->supports($editorial));
    }

    #[Test]
    public function it_fetches_photos_in_parallel_and_sets_them_on_context(): void
    {
        $photo1 = $this->createMock(Photo::class);
        $photo2 = $this->createMock(Photo::class);

        $promise1 = new FulfilledPromise($photo1);
        $promise2 = new FulfilledPromise($photo2);

        $this->queryMultimediaClient
            ->expects(self::exactly(2))
            ->method('findPhotoById')
            ->willReturnCallback(function (string $id, bool $async) use ($promise1, $promise2): PromiseInterface {
                self::assertTrue($async, 'Expected async: true parameter');

                return $id === 'photo-1' ? $promise1 : $promise2;
            });

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveAll')
            ->willReturn(new BatchResult([
                'photo-1' => $photo1,
                'photo-2' => $photo2,
            ], []));

        $context = $this->createContext(['photo-1', 'photo-2']);

        $this->enricher->enrich($context);

        self::assertSame(['photo-1' => $photo1, 'photo-2' => $photo2], $context->getPhotoBodyTags());
    }

    #[Test]
    public function it_handles_photo_fetch_failure_gracefully(): void
    {
        $photo1 = $this->createMock(Photo::class);
        $promise1 = new FulfilledPromise($photo1);
        $promise2 = new FulfilledPromise(null);

        $this->queryMultimediaClient
            ->expects(self::exactly(2))
            ->method('findPhotoById')
            ->willReturnCallback(fn(string $id): PromiseInterface => $id === 'photo-1' ? $promise1 : $promise2);

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveAll')
            ->willReturn(new BatchResult(
                ['photo-1' => $photo1],
                ['photo-2' => new \RuntimeException('Photo not found')]
            ));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to fetch photo from body tag',
                self::callback(fn(array $ctx): bool => $ctx['photo_id'] === 'photo-2')
            );

        $context = $this->createContext(['photo-1', 'photo-2']);

        $this->enricher->enrich($context);

        // Only the successful photo should be in context
        self::assertSame(['photo-1' => $photo1], $context->getPhotoBodyTags());
    }

    #[Test]
    public function it_sets_empty_array_when_no_photos_are_found(): void
    {
        $this->queryMultimediaClient
            ->expects(self::never())
            ->method('findPhotoById');

        $this->promiseResolver
            ->expects(self::never())
            ->method('resolveAll');

        $context = $this->createContext([]);

        $this->enricher->enrich($context);

        self::assertSame([], $context->getPhotoBodyTags());
    }

    #[Test]
    public function it_deduplicates_photo_ids(): void
    {
        $photo = $this->createMock(Photo::class);
        $promise = new FulfilledPromise($photo);

        // Same photo ID appears twice, should only fetch once
        $this->queryMultimediaClient
            ->expects(self::once())
            ->method('findPhotoById')
            ->with('photo-1', true)
            ->willReturn($promise);

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveAll')
            ->with(self::callback(fn(array $promises): bool => \count($promises) === 1))
            ->willReturn(new BatchResult(['photo-1' => $photo], []));

        // Create context with duplicate photo IDs
        $context = $this->createContextWithDuplicates(['photo-1', 'photo-1']);

        $this->enricher->enrich($context);

        self::assertSame(['photo-1' => $photo], $context->getPhotoBodyTags());
    }

    /**
     * @param array<int, string> $photoIds
     */
    private function createContext(array $photoIds): EditorialContext
    {
        return new EditorialContext(
            $this->createEditorialWithPhotos($photoIds),
            $this->createMock(Section::class),
            new EmbeddedContentDTO()
        );
    }

    /**
     * @param array<int, string> $photoIds
     */
    private function createContextWithDuplicates(array $photoIds): EditorialContext
    {
        return new EditorialContext(
            $this->createEditorialWithDuplicatePhotos($photoIds),
            $this->createMock(Section::class),
            new EmbeddedContentDTO()
        );
    }

    /**
     * @param array<int, string> $photoIds
     */
    private function createEditorialWithPhotos(array $photoIds): Editorial
    {
        $bodyTagPictures = [];
        foreach ($photoIds as $photoId) {
            $pictureId = $this->createMock(BodyTagPictureId::class);
            $pictureId->method('id')->willReturn($photoId);

            $bodyTagPicture = $this->createMock(BodyTagPicture::class);
            $bodyTagPicture->method('id')->willReturn($pictureId);
            $bodyTagPictures[] = $bodyTagPicture;
        }

        $body = $this->createMock(Body::class);
        $body->method('bodyElementsOf')
            ->willReturnCallback(function (string $class) use ($bodyTagPictures): array {
                if ($class === BodyTagPicture::class) {
                    return $bodyTagPictures;
                }

                return [];
            });

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('body')->willReturn($body);

        return $editorial;
    }

    /**
     * @param array<int, string> $photoIds
     */
    private function createEditorialWithDuplicatePhotos(array $photoIds): Editorial
    {
        $bodyTagPictures = [];
        foreach ($photoIds as $photoId) {
            $pictureId = $this->createMock(BodyTagPictureId::class);
            $pictureId->method('id')->willReturn($photoId);

            $bodyTagPicture = $this->createMock(BodyTagPicture::class);
            $bodyTagPicture->method('id')->willReturn($pictureId);
            $bodyTagPictures[] = $bodyTagPicture;
        }

        $body = $this->createMock(Body::class);
        $body->method('bodyElementsOf')
            ->willReturnCallback(function (string $class) use ($bodyTagPictures): array {
                if ($class === BodyTagPicture::class) {
                    return $bodyTagPictures;
                }

                return [];
            });

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('body')->willReturn($body);

        return $editorial;
    }

    private function createEditorialWithBodyElements(bool $hasPictures, bool $hasMembershipCards): Editorial
    {
        $body = $this->createMock(Body::class);
        $body->method('bodyElementsOf')
            ->willReturnCallback(function (string $class) use ($hasPictures, $hasMembershipCards): array {
                if ($class === BodyTagPicture::class) {
                    return $hasPictures ? [$this->createMock(BodyTagPicture::class)] : [];
                }
                if ($class === BodyTagMembershipCard::class) {
                    return $hasMembershipCards ? [$this->createMock(BodyTagMembershipCard::class)] : [];
                }

                return [];
            });

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('body')->willReturn($body);

        return $editorial;
    }
}
