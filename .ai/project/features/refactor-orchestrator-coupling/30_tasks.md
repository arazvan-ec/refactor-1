# Tasks: Refactor EditorialOrchestrator Coupling

**Feature ID**: refactor-orchestrator-coupling
**Created**: 2026-01-27

---

## Phase 1: Foundation (Infrastructure)

### Task 1.1: Create ContentEnricherInterface
**File**: `src/Orchestrator/Enricher/ContentEnricherInterface.php`
**Estimated complexity**: LOW

```php
interface ContentEnricherInterface
{
    /**
     * Enrich the editorial context with additional data.
     */
    public function enrich(EditorialContext $context): void;

    /**
     * Check if this enricher supports the given editorial.
     */
    public function supports(Editorial $editorial): bool;

    /**
     * Get the priority for ordering enrichers.
     * Higher priority = executed first.
     */
    public function getPriority(): int;
}
```

**Tests**: `tests/Unit/Orchestrator/Enricher/ContentEnricherInterfaceTest.php`

---

### Task 1.2: Create EditorialContext DTO
**File**: `src/Orchestrator/DTO/EditorialContext.php`
**Estimated complexity**: MEDIUM

```php
final class EditorialContext
{
    // Input data (readonly)
    public readonly Editorial $editorial;
    public readonly Section $section;
    public readonly EmbeddedContent $embeddedContent;

    // Enriched data (mutable)
    private array $tags = [];
    private ?array $membershipLinks = null;
    private array $photoBodyTags = [];
    private array $resolvedMultimedia = [];
    private array $customData = [];

    // Builder methods
    public function withTags(array $tags): void;
    public function withMembershipLinks(?array $links): void;
    public function withPhotoBodyTags(array $photos): void;
    public function addCustomData(string $key, mixed $value): void;

    // Accessor methods
    public function getTags(): array;
    public function getMembershipLinks(): ?array;
    public function getPhotoBodyTags(): array;
    public function getCustomData(string $key): mixed;
    public function getAllCustomData(): array;
}
```

**Tests**: `tests/Unit/Orchestrator/DTO/EditorialContextTest.php`

---

### Task 1.3: Create ContentEnricherCompiler
**File**: `src/DependencyInjection/Compiler/ContentEnricherCompiler.php`
**Estimated complexity**: MEDIUM

```php
final class ContentEnricherCompiler implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ContentEnricherChainHandler::class)) {
            return;
        }

        $definition = $container->findDefinition(ContentEnricherChainHandler::class);
        $taggedServices = $container->findTaggedServiceIds('app.content_enricher');

        $enrichers = [];
        foreach ($taggedServices as $id => $tags) {
            $priority = $tags[0]['priority'] ?? 0;
            $enrichers[$priority][] = new Reference($id);
        }

        krsort($enrichers); // Higher priority first
        $sortedEnrichers = array_merge(...$enrichers);

        $definition->setArgument('$enrichers', $sortedEnrichers);
    }
}
```

**Tests**: `tests/Unit/DependencyInjection/Compiler/ContentEnricherCompilerTest.php`

---

### Task 1.4: Create ContentEnricherChainHandler
**File**: `src/Orchestrator/Enricher/ContentEnricherChainHandler.php`
**Estimated complexity**: LOW

```php
final class ContentEnricherChainHandler
{
    /** @param iterable<ContentEnricherInterface> $enrichers */
    public function __construct(
        private readonly iterable $enrichers,
        private readonly LoggerInterface $logger,
    ) {}

    public function enrichAll(EditorialContext $context): EditorialContext
    {
        foreach ($this->enrichers as $enricher) {
            if ($enricher->supports($context->editorial)) {
                try {
                    $enricher->enrich($context);
                } catch (\Throwable $e) {
                    $this->logger->error(sprintf(
                        'Enricher %s failed: %s',
                        $enricher::class,
                        $e->getMessage()
                    ));
                }
            }
        }

        return $context;
    }
}
```

**Tests**: `tests/Unit/Orchestrator/Enricher/ContentEnricherChainHandlerTest.php`

---

## Phase 2: Extract Enrichers

### Task 2.1: Create TagsEnricher
**File**: `src/Orchestrator/Enricher/TagsEnricher.php`
**Estimated complexity**: LOW

Extract from `EditorialOrchestrator::fetchTags()`:

```php
#[AutoconfigureTag('app.content_enricher', ['priority' => 100])]
final class TagsEnricher implements ContentEnricherInterface
{
    public function __construct(
        private readonly QueryTagClient $queryTagClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function enrich(EditorialContext $context): void
    {
        $tags = [];
        foreach ($context->editorial->tags()->getArrayCopy() as $tag) {
            try {
                $tags[] = $this->queryTagClient->findTagById($tag->id());
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to fetch tag: ' . $e->getMessage());
            }
        }
        $context->withTags($tags);
    }

    public function supports(Editorial $editorial): bool
    {
        return !$editorial->tags()->isEmpty();
    }

    public function getPriority(): int
    {
        return 100;
    }
}
```

**Tests**: `tests/Unit/Orchestrator/Enricher/TagsEnricherTest.php`

---

### Task 2.2: Create MembershipLinksEnricher
**File**: `src/Orchestrator/Enricher/MembershipLinksEnricher.php`
**Estimated complexity**: MEDIUM

Extract from `EditorialOrchestrator::getPromiseMembershipLinks()` + `getLinksFromBody()`:

```php
#[AutoconfigureTag('app.content_enricher', ['priority' => 90])]
final class MembershipLinksEnricher implements ContentEnricherInterface
{
    public function __construct(
        private readonly QueryMembershipClient $membershipClient,
        private readonly PromiseResolverInterface $promiseResolver,
        private readonly UriFactoryInterface $uriFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function enrich(EditorialContext $context): void
    {
        $linksData = $this->getLinksFromBody($context->editorial->body());

        if (empty($linksData)) {
            $context->withMembershipLinks([]);
            return;
        }

        $uris = array_map(
            fn(string $link) => $this->uriFactory->createUri($link),
            $linksData
        );

        $promise = $this->membershipClient->getMembershipUrl(
            $context->editorial->id()->id(),
            $uris,
            SitesEnum::getEncodenameById($context->section->siteId()),
            true
        );

        $resolved = $this->promiseResolver->resolveMembershipLinks($promise, $linksData);
        $context->withMembershipLinks($resolved);
    }

    // ... private helper methods moved from EditorialOrchestrator
}
```

**Tests**: `tests/Unit/Orchestrator/Enricher/MembershipLinksEnricherTest.php`

---

### Task 2.3: Create PhotoBodyTagsEnricher
**File**: `src/Orchestrator/Enricher/PhotoBodyTagsEnricher.php`
**Estimated complexity**: MEDIUM

Extract from `EditorialOrchestrator::retrievePhotosFromBodyTags()` + `addPhotoToArray()`:

```php
#[AutoconfigureTag('app.content_enricher', ['priority' => 80])]
final class PhotoBodyTagsEnricher implements ContentEnricherInterface
{
    public function __construct(
        private readonly QueryMultimediaClient $multimediaClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function enrich(EditorialContext $context): void
    {
        $photos = [];
        $body = $context->editorial->body();

        foreach ($body->bodyElementsOf(BodyTagPicture::class) as $picture) {
            $photos = $this->addPhoto($picture->id()->id(), $photos);
        }

        foreach ($body->bodyElementsOf(BodyTagMembershipCard::class) as $card) {
            $id = $card->bodyTagPictureMembership()->id()->id();
            $photos = $this->addPhoto($id, $photos);
        }

        $context->withPhotoBodyTags($photos);
    }

    private function addPhoto(string $id, array $result): array
    {
        try {
            $result[$id] = $this->multimediaClient->findPhotoById($id);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch photo: ' . $e->getMessage());
        }
        return $result;
    }

    // ...
}
```

**Tests**: `tests/Unit/Orchestrator/Enricher/PhotoBodyTagsEnricherTest.php`

---

## Phase 3: Refactor EditorialOrchestrator

### Task 3.1: Update EditorialOrchestrator
**File**: `src/Orchestrator/Chain/EditorialOrchestrator.php`
**Estimated complexity**: HIGH

**Before (278 lines, 11 dependencies)**:
```php
public function __construct(
    private readonly EditorialFetcherInterface $editorialFetcher,
    private readonly EmbeddedContentFetcherInterface $embeddedContentFetcher,
    private readonly PromiseResolverInterface $promiseResolver,
    private readonly ResponseAggregatorInterface $responseAggregator,
    private readonly QueryTagClient $queryTagClient,           // ❌ Remove
    private readonly QueryMembershipClient $queryMembershipClient,  // ❌ Remove
    private readonly QueryMultimediaClient $queryMultimediaClient,  // ❌ Remove
    private readonly UriFactoryInterface $uriFactory,          // ❌ Remove
    private readonly LoggerInterface $logger,
    private readonly SignatureFetcherInterface $signatureFetcher,
    private readonly CommentsFetcherInterface $commentsFetcher,
) {}
```

**After (~100 lines, 7 dependencies)**:
```php
public function __construct(
    private readonly EditorialFetcherInterface $editorialFetcher,
    private readonly EmbeddedContentFetcherInterface $embeddedContentFetcher,
    private readonly PromiseResolverInterface $promiseResolver,
    private readonly ResponseAggregatorInterface $responseAggregator,
    private readonly ContentEnricherChainHandler $enricherChain,  // ✅ New
    private readonly SignatureFetcherInterface $signatureFetcher,
    private readonly CommentsFetcherInterface $commentsFetcher,
) {}

public function execute(Request $request): array
{
    $id = $request->get('id');
    $fetchedEditorial = $this->editorialFetcher->fetch($id);

    if ($this->editorialFetcher->shouldUseLegacy($fetchedEditorial->editorial)) {
        return $this->editorialFetcher->fetchLegacy($id);
    }

    // Create context
    $context = new EditorialContext(
        editorial: $fetchedEditorial->editorial,
        section: $fetchedEditorial->section,
        embeddedContent: $this->embeddedContentFetcher->fetch(
            $fetchedEditorial->editorial,
            $fetchedEditorial->section
        ),
    );

    // Enrich context (all HTTP calls happen here, but decoupled)
    $this->enricherChain->enrichAll($context);

    // Resolve promises
    $resolvedMultimedia = $this->promiseResolver->resolveMultimedia(
        $context->embeddedContent->multimediaPromises
    );

    // Build pre-fetched data DTO
    $preFetchedData = new PreFetchedDataDTO(
        commentsCount: $this->commentsFetcher->fetchCommentsCount(
            $context->editorial->id()->id()
        ),
        signatures: $this->signatureFetcher->fetchSignatures(
            $context->editorial,
            $context->section
        ),
    );

    // Aggregate response
    return $this->responseAggregator->aggregateFromContext(
        $context,
        $resolvedMultimedia,
        $preFetchedData,
    );
}
```

**Tests**: Update existing `EditorialOrchestratorTest.php`

---

### Task 3.2: Update ResponseAggregator
**File**: `src/Application/Service/Editorial/ResponseAggregator.php`
**Estimated complexity**: MEDIUM

Add new method that accepts `EditorialContext`:

```php
public function aggregateFromContext(
    EditorialContext $context,
    array $resolvedMultimedia,
    PreFetchedDataDTO $preFetchedData,
): array {
    return $this->aggregate(
        new FetchedEditorialDTO(
            $context->editorial,
            $context->section,
        ),
        $context->embeddedContent,
        $context->getTags(),
        $resolvedMultimedia,
        $context->getMembershipLinks(),
        $context->getPhotoBodyTags(),
        $preFetchedData,
    );
}
```

**Tests**: Update `ResponseAggregatorTest.php`

---

## Phase 4: Configuration & Tests

### Task 4.1: Register Compiler Pass
**File**: `src/Kernel.php` or `config/services.yaml`
**Estimated complexity**: LOW

```php
// In Kernel.php build() method
$container->addCompilerPass(new ContentEnricherCompiler());
```

Or via autoconfigure tag in services.yaml:
```yaml
services:
    _instanceof:
        App\Orchestrator\Enricher\ContentEnricherInterface:
            tags: ['app.content_enricher']
```

---

### Task 4.2: Add Architecture Tests
**File**: `tests/Architecture/EnricherLayerArchitectureTest.php`
**Estimated complexity**: LOW

```php
public function testEnrichersCanInjectHttpClients(): void
{
    // Enrichers ARE allowed to have HTTP clients
    // This is the designated place for HTTP calls
}

public function testEditorialOrchestratorDoesNotInjectHttpClients(): void
{
    // After refactor, EditorialOrchestrator should have NO HTTP clients
}
```

---

### Task 4.3: Regression Tests
**Estimated complexity**: MEDIUM

1. Run existing integration tests
2. Compare API response before/after
3. Verify all fields present and correct

---

## Summary

| Phase | Tasks | Estimated Files | Risk |
|-------|-------|-----------------|------|
| **1. Foundation** | 4 | 4 new files + 4 tests | LOW |
| **2. Extract Enrichers** | 3 | 3 new files + 3 tests | LOW |
| **3. Refactor** | 2 | 2 modified files | MEDIUM |
| **4. Config & Tests** | 3 | 2 new + config | LOW |

**Total**: 12 tasks, ~12 new/modified files

---

## Dependency Graph

```
Task 1.1 (Interface) ──┐
                       ├──► Task 1.4 (ChainHandler) ──┐
Task 1.2 (Context) ────┤                              │
                       │                              ├──► Task 3.1 (Refactor Orchestrator)
Task 1.3 (Compiler) ───┘                              │
                                                      │
Task 2.1 (TagsEnricher) ──────────────────────────────┤
Task 2.2 (MembershipEnricher) ────────────────────────┤
Task 2.3 (PhotoEnricher) ─────────────────────────────┘

Task 3.1 ──► Task 3.2 (Update Aggregator) ──► Task 4.x (Tests)
```

**Paralelizable**:
- Tasks 2.1, 2.2, 2.3 pueden ejecutarse en paralelo después de Phase 1
