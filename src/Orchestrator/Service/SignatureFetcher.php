<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use Ec\Editorial\Domain\Model\EditorialBlog;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Section\Domain\Model\Section;
use Psr\Log\LoggerInterface;

/**
 * Fetches and transforms journalist signatures for editorials.
 *
 * This service lives in the Orchestrator layer because it makes HTTP calls
 * to the journalist service. The transformed signatures are then passed
 * to the ResponseAggregator which only does data transformation.
 */
final class SignatureFetcher implements SignatureFetcherInterface
{
    private const TWITTER_TYPES = [EditorialBlog::EDITORIAL_TYPE];

    public function __construct(
        private readonly QueryJournalistClient $journalistClient,
        private readonly JournalistFactory $journalistFactory,
        private readonly JournalistsDataTransformer $journalistsDataTransformer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fetchSignatures(NewsBase $editorial, Section $section): array
    {
        $signatures = [];
        $hasTwitter = \in_array($editorial->editorialType(), self::TWITTER_TYPES, true);

        /** @var Signature $signature */
        foreach ($editorial->signatures()->getArrayCopy() as $signature) {
            $result = $this->fetchAndTransformSignature(
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
     * Fetch journalist and transform to response format.
     *
     * @return array<string, mixed>
     */
    private function fetchAndTransformSignature(string $aliasId, Section $section, bool $hasTwitter): array
    {
        try {
            $aliasIdModel = $this->journalistFactory->buildAliasId($aliasId);

            /** @var Journalist $journalist */
            $journalist = $this->journalistClient->findJournalistByAliasId($aliasIdModel);

            return $this->journalistsDataTransformer
                ->write($aliasId, $journalist, $section, $hasTwitter)
                ->read();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch journalist signature: ' . $e->getMessage());

            return [];
        }
    }
}
