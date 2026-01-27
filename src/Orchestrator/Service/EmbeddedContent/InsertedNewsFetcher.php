<?php

declare(strict_types=1);

namespace App\Orchestrator\Service\EmbeddedContent;

use App\Application\DTO\EmbeddedEditorialDTO;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\NewsBase;

/**
 * Fetches inserted news (BodyTagInsertedNews) from an editorial's body.
 *
 * Single responsibility: extract and fetch all inserted news references.
 */
final class InsertedNewsFetcher implements InsertedNewsFetcherInterface
{
    public function __construct(
        private readonly EmbeddedEditorialFetcherInterface $embeddedEditorialFetcher,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(NewsBase $editorial): array
    {
        $editorials = [];
        $promises = [];

        /** @var BodyTagInsertedNews[] $insertedNewsTags */
        $insertedNewsTags = $editorial->body()->bodyElementsOf(BodyTagInsertedNews::class);

        foreach ($insertedNewsTags as $insertedNewsTag) {
            $result = $this->embeddedEditorialFetcher->fetch($insertedNewsTag->editorialId()->id());

            if (null !== $result) {
                $editorials[$result['dto']->id] = $result['dto'];
                $promises = array_merge($promises, $result['promises']);
            }
        }

        return ['editorials' => $editorials, 'promises' => $promises];
    }
}
