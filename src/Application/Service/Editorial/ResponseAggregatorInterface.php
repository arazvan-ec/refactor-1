<?php

declare(strict_types=1);

namespace App\Application\Service\Editorial;

use App\Application\DTO\EmbeddedContentDTO;
use App\Application\DTO\FetchedEditorialDTO;
use Ec\Tag\Domain\Model\Tag;

/**
 * Aggregates all fetched data into final editorial response.
 *
 * Coordinates transformers and builds the complete API response.
 */
interface ResponseAggregatorInterface
{
    /**
     * Aggregate all data into final response array.
     *
     * @param FetchedEditorialDTO $fetchedEditorial Editorial and section
     * @param EmbeddedContentDTO $embeddedContent Inserted news, recommended, multimedia
     * @param array<int, Tag> $tags Associated tags
     * @param array<string, mixed> $resolvedMultimedia Resolved multimedia data
     * @param array<string, mixed> $membershipLinks Resolved membership links
     * @param array<string, mixed> $photoBodyTags Photos from body tags
     *
     * @return array<string, mixed> Complete response array
     */
    public function aggregate(
        FetchedEditorialDTO $fetchedEditorial,
        EmbeddedContentDTO $embeddedContent,
        array $tags,
        array $resolvedMultimedia,
        array $membershipLinks,
        array $photoBodyTags,
    ): array;
}
