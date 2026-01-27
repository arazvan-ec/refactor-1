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
     * @param array<string, string> $membershipLinks Resolved membership links
     * @param array<string, mixed> $photoBodyTags Photos from body tags
     *
     * @return array{
     *   id: string,
     *   url: string,
     *   titles: array{title: string, preTitle: string, urlTitle: string, mobileTitle: string},
     *   lead: string,
     *   publicationDate: string,
     *   updatedOn: string,
     *   endOn: string,
     *   type: array{id: string, name: string},
     *   indexable: bool,
     *   deleted: bool,
     *   published: bool,
     *   closingModeId: string,
     *   commentable: bool,
     *   isBrand: bool,
     *   isAmazonOnsite: bool,
     *   contentType: string,
     *   canonicalEditorialId: string,
     *   urlDate: string,
     *   countWords: int,
     *   countComments: int,
     *   section: array{id: string, name: string, url: string, encodeName: string},
     *   tags: list<array{id: string, name: string, url: string}>,
     *   signatures: list<array{id: string, name: string, picture: string|null, url: string, twitter?: string}>,
     *   body: list<array{type: string, content?: string}>,
     *   multimedia: array{id: string, type: string, caption: string, shots: object}|null,
     *   standfirst: list<array{type: string, content: string}>,
     *   recommendedEditorials: list<array{id: string, title: string, url: string, image?: string}>,
     *   adsOptions: list<string>,
     *   analiticsOptions: list<string>
     * }
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
