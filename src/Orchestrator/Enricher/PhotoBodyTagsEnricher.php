<?php

declare(strict_types=1);

namespace App\Orchestrator\Enricher;

use App\Orchestrator\DTO\EditorialContext;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Multimedia\Domain\Model\Photo;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Enricher that fetches photos referenced in body tags.
 *
 * Extracts photo IDs from BodyTagPicture and BodyTagMembershipCard
 * elements and fetches the complete Photo data from Multimedia service.
 */
#[AutoconfigureTag('app.content_enricher', ['priority' => 80])]
final class PhotoBodyTagsEnricher implements ContentEnricherInterface
{
    public function __construct(
        private readonly QueryMultimediaClient $queryMultimediaClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enrich(EditorialContext $context): void
    {
        $photos = $this->retrievePhotosFromBodyTags($context->editorial->body());
        $context->withPhotoBodyTags($photos);
    }

    public function supports(Editorial $editorial): bool
    {
        $body = $editorial->body();

        // Check if there are any photo tags or membership cards with photos
        $hasPictures = !empty($body->bodyElementsOf(BodyTagPicture::class));
        $hasMembershipCards = !empty($body->bodyElementsOf(BodyTagMembershipCard::class));

        return $hasPictures || $hasMembershipCards;
    }

    public function getPriority(): int
    {
        return 80;
    }

    /**
     * Retrieve photos from body tags.
     *
     * @return array<string, Photo>
     */
    private function retrievePhotosFromBodyTags(Body $body): array
    {
        $result = [];

        /** @var BodyTagPicture[] $arrayOfBodyTagPicture */
        $arrayOfBodyTagPicture = $body->bodyElementsOf(BodyTagPicture::class);
        foreach ($arrayOfBodyTagPicture as $bodyTagPicture) {
            $result = $this->addPhotoToArray($bodyTagPicture->id()->id(), $result);
        }

        /** @var BodyTagMembershipCard[] $arrayOfBodyTagMembershipCard */
        $arrayOfBodyTagMembershipCard = $body->bodyElementsOf(BodyTagMembershipCard::class);
        foreach ($arrayOfBodyTagMembershipCard as $bodyTagMembershipCard) {
            $id = $bodyTagMembershipCard->bodyTagPictureMembership()->id()->id();
            $result = $this->addPhotoToArray($id, $result);
        }

        return $result;
    }

    /**
     * Add a photo to the result array.
     *
     * @param array<string, Photo> $result
     *
     * @return array<string, Photo>
     */
    private function addPhotoToArray(string $id, array $result): array
    {
        // Skip if already fetched
        if (isset($result[$id])) {
            return $result;
        }

        try {
            $photo = $this->queryMultimediaClient->findPhotoById($id);
            $result[$id] = $photo;
        } catch (\Throwable $throwable) {
            $this->logger->error(
                'Failed to fetch photo from body tag',
                [
                    'photo_id' => $id,
                    'error' => $throwable->getMessage(),
                ]
            );
        }

        return $result;
    }
}
