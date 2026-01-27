<?php

declare(strict_types=1);

namespace App\Orchestrator\Enricher;

use App\Application\Service\Promise\PromiseResolverInterface;
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
 * Enricher that fetches photos referenced in body tags in parallel.
 *
 * Extracts photo IDs from BodyTagPicture and BodyTagMembershipCard
 * elements and fetches the complete Photo data from Multimedia service.
 * Uses async promises to fetch all photos concurrently.
 */
#[AutoconfigureTag('app.content_enricher', ['priority' => 80])]
final class PhotoBodyTagsEnricher implements ContentEnricherInterface
{
    public function __construct(
        private readonly QueryMultimediaClient $queryMultimediaClient,
        private readonly PromiseResolverInterface $promiseResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enrich(EditorialContext $context): void
    {
        $photoIds = $this->extractPhotoIds($context->editorial->body());

        if (empty($photoIds)) {
            $context->withPhotoBodyTags([]);

            return;
        }

        // Create promises for all photos (parallel execution)
        $promises = [];
        foreach ($photoIds as $id) {
            $promises[$id] = $this->queryMultimediaClient->findPhotoById($id, async: true);
        }

        // Resolve all promises in parallel
        $result = $this->promiseResolver->resolveAll($promises);

        // Log rejected photos
        foreach ($result->rejected as $photoId => $error) {
            $this->logger->error(
                'Failed to fetch photo from body tag',
                [
                    'photo_id' => $photoId,
                    'error' => $error->getMessage(),
                ]
            );
        }

        $context->withPhotoBodyTags($result->fulfilled);
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
     * Extract unique photo IDs from body tags.
     *
     * @return array<int, string>
     */
    private function extractPhotoIds(Body $body): array
    {
        $ids = [];

        /** @var BodyTagPicture[] $arrayOfBodyTagPicture */
        $arrayOfBodyTagPicture = $body->bodyElementsOf(BodyTagPicture::class);
        foreach ($arrayOfBodyTagPicture as $bodyTagPicture) {
            $ids[] = $bodyTagPicture->id()->id();
        }

        /** @var BodyTagMembershipCard[] $arrayOfBodyTagMembershipCard */
        $arrayOfBodyTagMembershipCard = $body->bodyElementsOf(BodyTagMembershipCard::class);
        foreach ($arrayOfBodyTagMembershipCard as $bodyTagMembershipCard) {
            $ids[] = $bodyTagMembershipCard->bodyTagPictureMembership()->id()->id();
        }

        return array_unique($ids);
    }
}
