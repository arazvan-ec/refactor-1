<?php

declare(strict_types=1);

namespace App\Application\Service\Editorial;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\Apps\Media\MediaDataTransformerHandler;
use App\Application\DataTransformer\Apps\MultimediaDataTransformer;
use App\Application\DataTransformer\Apps\RecommendedEditorialsDataTransformer;
use App\Application\DataTransformer\Apps\StandfirstDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Application\DTO\EmbeddedContentDTO;
use App\Application\DTO\FetchedEditorialDTO;
use App\Application\DTO\PreFetchedDataDTO;
use Ec\Editorial\Domain\Model\Multimedia\Widget;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Exceptions\MultimediaDataTransformerNotFoundException;

/**
 * Aggregates all fetched data into final editorial response.
 *
 * Coordinates all transformers to build the complete API response.
 * Extracted from EditorialOrchestrator to improve single responsibility.
 *
 * IMPORTANT: This class must NOT make HTTP calls. All external data must
 * be pre-fetched by the Orchestrator and passed via parameters/DTOs.
 */
final class ResponseAggregator implements ResponseAggregatorInterface
{
    public function __construct(
        private readonly AppsDataTransformer $appsDataTransformer,
        private readonly BodyDataTransformer $bodyDataTransformer,
        private readonly MultimediaDataTransformer $multimediaDataTransformer,
        private readonly StandfirstDataTransformer $standfirstDataTransformer,
        private readonly RecommendedEditorialsDataTransformer $recommendedEditorialsDataTransformer,
        private readonly MediaDataTransformerHandler $mediaDataTransformerHandler,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param array<int, \Ec\Tag\Domain\Model\Tag> $tags
     * @param array<string, mixed> $resolvedMultimedia
     * @param array<string, string> $membershipLinks
     * @param array<string, mixed> $photoBodyTags
     */
    public function aggregate(
        FetchedEditorialDTO $fetchedEditorial,
        EmbeddedContentDTO $embeddedContent,
        array $tags,
        array $resolvedMultimedia,
        array $membershipLinks,
        array $photoBodyTags,
        PreFetchedDataDTO $preFetchedData,
    ): array {
        $editorial = $fetchedEditorial->editorial;
        $section = $fetchedEditorial->section;

        // Build base response from apps transformer
        $result = $this->appsDataTransformer
            ->write($editorial, $section, $tags)
            ->read();

        // Add pre-fetched data (no HTTP calls here!)
        $result['countComments'] = $preFetchedData->commentsCount;
        $result['signatures'] = $preFetchedData->signatures;

        // Build resolve data for body transformer
        $resolveData = $this->buildResolveData(
            $embeddedContent,
            $resolvedMultimedia,
            $membershipLinks,
            $photoBodyTags,
        );

        // Transform body
        $result['body'] = $this->bodyDataTransformer->execute(
            $editorial->body(),
            $resolveData
        );

        // Transform multimedia
        $result['multimedia'] = $this->transformMultimedia($editorial, $resolveData);

        // Transform standfirst
        $result['standfirst'] = $this->standfirstDataTransformer
            ->write($editorial->standFirst())
            ->read();

        // Transform recommended editorials
        $result['recommendedEditorials'] = $this->recommendedEditorialsDataTransformer
            ->write($embeddedContent->recommendedNews, $resolveData)
            ->read();

        return $result;
    }

    /**
     * Build the resolve data array for body transformer.
     *
     * @param array<string, mixed> $resolvedMultimedia
     * @param array<string, string> $membershipLinks
     * @param array<string, mixed> $photoBodyTags
     *
     * @return array{
     *   insertedNews: array<string, array{editorial: mixed, section: mixed, signatures: list<mixed>}>,
     *   recommendedNews: list<mixed>,
     *   multimedia: array<string, mixed>,
     *   multimediaOpening?: mixed,
     *   membershipLinkCombine: array<string, string>,
     *   photoFromBodyTags: array<string, mixed>
     * }
     */
    private function buildResolveData(
        EmbeddedContentDTO $embeddedContent,
        array $resolvedMultimedia,
        array $membershipLinks,
        array $photoBodyTags,
    ): array {
        $resolveData = $embeddedContent->toResolveDataArray();

        // Replace promises with resolved multimedia
        $resolveData['multimedia'] = $resolvedMultimedia;

        // Add membership links
        $resolveData['membershipLinkCombine'] = $membershipLinks;

        // Add photos from body tags
        $resolveData['photoFromBodyTags'] = $photoBodyTags;

        return $resolveData;
    }

    /**
     * Transform multimedia based on type and availability.
     *
     * @param array<string, mixed> $resolveData
     *
     * @return array<string, mixed>|null
     *
     * @throws MultimediaDataTransformerNotFoundException
     */
    private function transformMultimedia(NewsBase $editorial, array $resolveData): ?array
    {
        // Check for opening multimedia first
        if (!empty($resolveData['multimediaOpening'])) {
            return $this->mediaDataTransformerHandler->execute(
                $resolveData['multimediaOpening'],
                $editorial->opening()
            );
        }

        // Fall back to regular multimedia
        if (!empty($resolveData['multimedia']) && !($editorial->multimedia() instanceof Widget)) {
            return $this->multimediaDataTransformer
                ->write($resolveData['multimedia'], $editorial->multimedia())
                ->read();
        }

        return null;
    }
}
