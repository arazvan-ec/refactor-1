<?php

declare(strict_types=1);

namespace App\Orchestrator\Enricher;

use App\Application\Service\Promise\PromiseResolverInterface;
use App\Infrastructure\Enum\SitesEnum;
use App\Orchestrator\DTO\EditorialContext;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\MembershipCardButton;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Membership\Infrastructure\Client\Http\QueryMembershipClient;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Enricher that resolves membership links in the editorial body.
 *
 * Extracts membership card URLs from the body, fetches resolved URLs
 * from the Membership service, and stores them in the context.
 */
#[AutoconfigureTag('app.content_enricher', ['priority' => 90])]
final class MembershipLinksEnricher implements ContentEnricherInterface
{
    public function __construct(
        private readonly QueryMembershipClient $queryMembershipClient,
        private readonly PromiseResolverInterface $promiseResolver,
        private readonly UriFactoryInterface $uriFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enrich(EditorialContext $context): void
    {
        $linksData = $this->getLinksFromBody($context->editorial->body());

        if (empty($linksData)) {
            $context->withMembershipLinks([]);

            return;
        }

        $links = [];
        $uris = [];

        foreach ($linksData as $membershipLink) {
            try {
                $uris[] = $this->uriFactory->createUri($membershipLink);
                $links[] = $membershipLink;
            } catch (\Throwable $exception) {
                $this->logger->warning(
                    'Failed to create URI for membership link',
                    [
                        'link' => $membershipLink,
                        'error' => $exception->getMessage(),
                    ]
                );
            }
        }

        if (empty($uris)) {
            $context->withMembershipLinks([]);

            return;
        }

        $promise = $this->queryMembershipClient->getMembershipUrl(
            $context->editorial->id()->id(),
            $uris,
            SitesEnum::getEncodenameById($context->section->siteId()),
            true
        );

        $resolvedLinks = $this->promiseResolver->resolveMembershipLinks($promise, $links);
        $context->withMembershipLinks($resolvedLinks);
    }

    public function supports(Editorial $editorial): bool
    {
        // Check if there are any membership cards in the body
        $membershipCards = $editorial->body()->bodyElementsOf(BodyTagMembershipCard::class);

        return !empty($membershipCards);
    }

    public function getPriority(): int
    {
        return 90;
    }

    /**
     * Extract membership links from the editorial body.
     *
     * @return array<int, string>
     */
    private function getLinksFromBody(Body $body): array
    {
        $linksData = [];

        /** @var BodyTagMembershipCard[] $bodyElementsMembership */
        $bodyElementsMembership = $body->bodyElementsOf(BodyTagMembershipCard::class);

        foreach ($bodyElementsMembership as $bodyElement) {
            /** @var MembershipCardButton $button */
            foreach ($bodyElement->buttons()->buttons() as $button) {
                $linksData[] = $button->urlMembership();
                $linksData[] = $button->url();
            }
        }

        return $linksData;
    }
}
