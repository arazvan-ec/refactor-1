<?php

declare(strict_types=1);

namespace App\Application\Service\Editorial;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Application\DataTransformer\Apps\Media\MediaDataTransformerHandler;
use App\Application\DataTransformer\Apps\MultimediaDataTransformer;
use App\Application\DataTransformer\Apps\RecommendedEditorialsDataTransformer;
use App\Application\DataTransformer\Apps\StandfirstDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Application\DTO\EmbeddedContentDTO;
use App\Application\DTO\FetchedEditorialDTO;
use App\Infrastructure\Client\Legacy\QueryLegacyClient;
use Ec\Editorial\Domain\Model\EditorialBlog;
use Ec\Editorial\Domain\Model\Multimedia\Widget;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Editorial\Exceptions\MultimediaDataTransformerNotFoundException;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\Tag;
use Psr\Log\LoggerInterface;

/**
 * Aggregates all fetched data into final editorial response.
 *
 * Coordinates all transformers to build the complete API response.
 * Extracted from EditorialOrchestrator to improve single responsibility.
 */
final class ResponseAggregator implements ResponseAggregatorInterface
{
    private const TWITTER_TYPES = [EditorialBlog::EDITORIAL_TYPE];

    public function __construct(
        private readonly AppsDataTransformer $appsDataTransformer,
        private readonly BodyDataTransformer $bodyDataTransformer,
        private readonly JournalistsDataTransformer $journalistsDataTransformer,
        private readonly MultimediaDataTransformer $multimediaDataTransformer,
        private readonly StandfirstDataTransformer $standfirstDataTransformer,
        private readonly RecommendedEditorialsDataTransformer $recommendedEditorialsDataTransformer,
        private readonly MediaDataTransformerHandler $mediaDataTransformerHandler,
        private readonly QueryLegacyClient $legacyClient,
        private readonly QueryJournalistClient $journalistClient,
        private readonly JournalistFactory $journalistFactory,
        private readonly LoggerInterface $logger,
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
    ): array {
        $editorial = $fetchedEditorial->editorial;
        $section = $fetchedEditorial->section;

        // Build base response from apps transformer
        $result = $this->appsDataTransformer
            ->write($editorial, $section, $tags)
            ->read();

        // Add comments count
        $result['countComments'] = $this->getCommentsCount($editorial->id()->id());

        // Add signatures
        $result['signatures'] = $this->buildSignatures($editorial, $section);

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
     * Get comments count from legacy client.
     */
    private function getCommentsCount(string $editorialId): int
    {
        /** @var array{options: array{totalrecords?: int}} $comments */
        $comments = $this->legacyClient->findCommentsByEditorialId($editorialId);

        return $comments['options']['totalrecords'] ?? 0;
    }

    /**
     * Build signatures array for the editorial.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSignatures(NewsBase $editorial, Section $section): array
    {
        $signatures = [];
        $hasTwitter = \in_array($editorial->editorialType(), self::TWITTER_TYPES, true);

        /** @var Signature $signature */
        foreach ($editorial->signatures()->getArrayCopy() as $signature) {
            $result = $this->formatSignature(
                $signature->id()->id(),
                $section,
                $hasTwitter
            );

            if (!empty($result)) {
                $signatures[] = $result;
            }
        }

        return $signatures;
    }

    /**
     * Format a single signature.
     *
     * @return array<string, mixed>
     */
    private function formatSignature(string $aliasId, Section $section, bool $hasTwitter): array
    {
        try {
            $aliasIdModel = $this->journalistFactory->buildAliasId($aliasId);

            /** @var Journalist $journalist */
            $journalist = $this->journalistClient->findJournalistByAliasId($aliasIdModel);

            return $this->journalistsDataTransformer
                ->write($aliasId, $journalist, $section, $hasTwitter)
                ->read();
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return [];
        }
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
