# Body Elements HTTP Resolution - Analysis & Improvement Plan

**Project**: SNAAPI
**Date**: 2026-01-26
**Status**: ANALYSIS + PLAN

---

## 1. Current Architecture

### Flow Overview

```
EditorialOrchestrator.execute()
    │
    ├── 1. editorialFetcher.fetch()           [SYNC]
    │       └── Editorial + Section
    │
    ├── 2. embeddedContentFetcher.fetch()     [MIXED]
    │       ├── Inserted News editorials      [SYNC]
    │       ├── Recommended editorials        [SYNC]
    │       └── Multimedia PROMISES           [ASYNC - not resolved]
    │
    ├── 3. retrievePhotosFromBodyTags()       [SYNC - BLOCKING!]
    │       └── Loop: findPhotoById() × N
    │
    ├── 4. promiseResolver.resolve()          [WAIT]
    │       └── Utils::settle($promises)
    │
    └── 5. responseAggregator.aggregate()
            └── buildResolveData()            [array<string, mixed>]
                    │
                    ▼
            bodyDataTransformer.execute($body, $resolveData)
                    │
                    ▼
            Each BodyElementTransformer accesses $resolveData magically
```

### Body Elements Requiring External Data

| Element | Data Source | Current Fetch |
|---------|-------------|---------------|
| `BodyTagPicture` | Photo by ID | SYNC in `retrievePhotosFromBodyTags()` |
| `BodyTagInsertedNews` | Editorial + Multimedia | SYNC editorial, ASYNC multimedia |
| `BodyTagVideo` | Video metadata | Via multimedia promises |
| `BodyTagMembershipCard` | Membership URLs + Photo | SYNC photo, ASYNC membership |
| `BodyTagPictureDefault` | Photo with crop | SYNC in `retrievePhotosFromBodyTags()` |

---

## 2. Identified Problems

### Problem 1: Sync Photo Fetching (N+1 Pattern)

```php
// EditorialOrchestrator.php:196-214
private function retrievePhotosFromBodyTags(Body $body): array
{
    $result = [];
    $arrayOfBodyTagPicture = $body->bodyElementsOf(BodyTagPicture::class);

    foreach ($arrayOfBodyTagPicture as $bodyTagPicture) {
        // ❌ SYNC CALL IN LOOP - N+1 pattern!
        $photo = $this->queryMultimediaClient->findPhotoById($id);
        $result[$id] = $photo;
    }

    return $result;
}
```

**Impact**:
- 10 photos = 10 sequential HTTP calls
- Each call ~50ms = 500ms total (vs ~50ms if parallel)
- Blocks while other async work could happen

### Problem 2: Untyped Resolve Data

```php
// ResponseAggregator.php - buildResolveData returns:
$resolveData = [
    'insertedNews' => [...],           // array<string, mixed>
    'recommendedEditorials' => [...],  // array<string, mixed>
    'multimedia' => [...],             // array<string, mixed>
    'photoFromBodyTags' => [...],      // array<string, mixed>
    'membershipLinkCombine' => [...],  // array<string, mixed>
];
```

**Impact**:
- No type safety
- Magic key access: `$resolveData['insertedNews'][$editorialId]['multimedia']`
- Silent failures if keys don't exist
- Impossible to refactor safely

### Problem 3: Mixed Resolved/Unresolved Data

```php
// EmbeddedContentDTO contains:
public array $insertedNews;        // Already fetched editorials
public array $multimediaPromises;  // PROMISES - not resolved!
public array $multimediaOpening;   // Already fetched

// Confusing: some data is ready, some needs resolution
```

**Impact**:
- Mental overhead to track what's resolved
- Easy to accidentally use unresolved data
- Inconsistent access patterns

### Problem 4: Transformers Do Hidden Data Access

```php
// BodyTagInsertedNewsDataTransformer.php
public function read(): array
{
    // ❌ Magic key access without validation
    $currentInsertedNews = $this->resolveData()['insertedNews'][$editorialId];

    // ❌ Assumes structure exists
    $signatures = $currentInsertedNews['signatures'];
    $editorial = $currentInsertedNews['editorial'];

    // ❌ Another magic access
    $multimedia = $this->resolveData()['multimedia'][$multimediaId];
}
```

**Impact**:
- Hidden dependencies
- No compile-time checking
- Difficult to test in isolation

### Problem 5: Responsibilities Mixed in Orchestrator

```php
// EditorialOrchestrator does:
// 1. Fetch editorial (ok)
// 2. Coordinate fetchers (ok)
// 3. Fetch photos directly (❌ should be in a fetcher)
// 4. Resolve promises (ok)
// 5. Build resolve data (❌ should be in aggregator)
```

---

## 3. Proposed Architecture

### Target Flow

```
EditorialOrchestrator.execute()
    │
    ├── 1. editorialFetcher.fetch()           [SYNC]
    │
    ├── 2. bodyElementDataCollector.collect() [NEW - ASYNC]
    │       ├── Collect ALL body element IDs
    │       ├── Create PROMISES for photos    [ASYNC]
    │       ├── Create PROMISES for videos    [ASYNC]
    │       └── Create PROMISES for widgets   [ASYNC]
    │
    ├── 3. embeddedContentFetcher.fetch()     [ASYNC]
    │       └── All multimedia as promises
    │
    ├── 4. promiseResolver.resolveAll()       [SINGLE WAIT]
    │       └── Utils::settle(ALL promises)
    │
    └── 5. responseAggregator.aggregate()
            └── Creates TransformContextDTO   [TYPED]
                    │
                    ▼
            bodyDataTransformer.execute($body, $context)
                    │
                    ▼
            Transformers use typed context
```

### Key Changes

| Aspect | Before | After |
|--------|--------|-------|
| Photo fetch | SYNC in loop (N+1) | ASYNC promises (parallel) |
| Resolve data | `array<string, mixed>` | `TransformContextDTO` |
| Promise resolution | Multiple places | Single `resolveAll()` |
| Transformer access | Magic keys | Typed methods |

---

## 4. New Components

### 4.1 BodyElementDataCollector (NEW)

```php
/**
 * Collects ALL body element data requirements and creates promises
 * Replaces the sync loop in EditorialOrchestrator
 */
final class BodyElementDataCollector
{
    public function __construct(
        private readonly QueryMultimediaClient $multimediaClient,
        private readonly QueryWidgetClient $widgetClient,
    ) {}

    /**
     * Scans body and creates promises for ALL external data needs
     */
    public function collect(Body $body): BodyElementDataRequirements
    {
        $photoPromises = [];
        $videoPromises = [];
        $widgetPromises = [];

        foreach ($body->getArrayCopy() as $element) {
            match (true) {
                $element instanceof BodyTagPicture =>
                    $photoPromises[$element->id()->id()] = $this->createPhotoPromise($element),

                $element instanceof BodyTagVideo =>
                    $videoPromises[$element->id()->id()] = $this->createVideoPromise($element),

                $element instanceof BodyTagWidget =>
                    $widgetPromises[$element->id()->id()] = $this->createWidgetPromise($element),

                $element instanceof BodyTagMembershipCard =>
                    $photoPromises[$element->photoId()] = $this->createPhotoPromise($element),

                default => null,
            };
        }

        return new BodyElementDataRequirements(
            photoPromises: $photoPromises,
            videoPromises: $videoPromises,
            widgetPromises: $widgetPromises,
        );
    }

    private function createPhotoPromise(BodyTagPicture $element): Promise
    {
        // ASYNC - returns immediately
        return $this->multimediaClient->findPhotoById(
            $element->id()->id(),
            async: true  // ← KEY: async!
        );
    }
}
```

### 4.2 BodyElementDataRequirements (NEW DTO)

```php
/**
 * Holds all promises for body element data
 */
final readonly class BodyElementDataRequirements
{
    public function __construct(
        /** @var array<string, Promise> keyed by photo ID */
        public array $photoPromises,
        /** @var array<string, Promise> keyed by video ID */
        public array $videoPromises,
        /** @var array<string, Promise> keyed by widget ID */
        public array $widgetPromises,
    ) {}

    public function allPromises(): array
    {
        return array_merge(
            $this->photoPromises,
            $this->videoPromises,
            $this->widgetPromises,
        );
    }

    public function isEmpty(): bool
    {
        return empty($this->photoPromises)
            && empty($this->videoPromises)
            && empty($this->widgetPromises);
    }
}
```

### 4.3 Updated PromiseResolver

```php
final class PromiseResolver
{
    /**
     * Resolves ALL promises in a single wait
     */
    public function resolveAll(
        array $multimediaPromises,
        array $membershipPromises,
        BodyElementDataRequirements $bodyRequirements,
    ): ResolvedDataDTO {

        // Combine ALL promises
        $allPromises = [
            'multimedia' => $multimediaPromises,
            'membership' => $membershipPromises,
            'photos' => $bodyRequirements->photoPromises,
            'videos' => $bodyRequirements->videoPromises,
            'widgets' => $bodyRequirements->widgetPromises,
        ];

        // SINGLE WAIT for all
        $settled = Utils::settle(array_merge(...array_values($allPromises)))->wait();

        // Categorize results
        return new ResolvedDataDTO(
            multimedia: $this->extractByPrefix($settled, 'multimedia'),
            membership: $this->extractByPrefix($settled, 'membership'),
            photos: $this->extractByPrefix($settled, 'photos'),
            videos: $this->extractByPrefix($settled, 'videos'),
            widgets: $this->extractByPrefix($settled, 'widgets'),
        );
    }
}
```

### 4.4 ResolvedDataDTO (NEW)

```php
/**
 * All resolved external data, strongly typed
 */
final readonly class ResolvedDataDTO
{
    public function __construct(
        public MultimediaCollection $multimedia,
        public MembershipLinkCollection $membership,
        public PhotoCollection $photos,
        public VideoCollection $videos,
        public WidgetCollection $widgets,
    ) {}
}
```

### 4.5 Updated TransformContextDTO

```php
/**
 * Context passed to body element transformers
 * Fully typed, no magic key access
 */
final readonly class TransformContextDTO
{
    public function __construct(
        public PhotoCollection $photos,
        public VideoCollection $videos,
        public WidgetCollection $widgets,
        public MultimediaCollection $multimedia,
        public InsertedNewsCollection $insertedNews,
        public RecommendedNewsCollection $recommendedNews,
        public MembershipLinkCollection $membershipLinks,
    ) {}

    public function getPhoto(string $photoId): ?Photo
    {
        return $this->photos->get($photoId);
    }

    public function getInsertedNews(string $editorialId): ?InsertedNewsDTO
    {
        return $this->insertedNews->get($editorialId);
    }

    public function getMultimedia(string $multimediaId): ?Multimedia
    {
        return $this->multimedia->get($multimediaId);
    }
}
```

### 4.6 Updated BodyTagPictureDataTransformer

```php
final class BodyTagPictureDataTransformer implements BodyElementDataTransformerInterface
{
    public function transform(
        BodyElement $element,
        TransformContextDTO $context,  // ← TYPED context
    ): BodyTagPictureResponseDTO {

        /** @var BodyTagPicture $element */
        $photoId = $element->id()->id();

        // ✅ Typed access with null safety
        $photo = $context->getPhoto($photoId);

        if (null === $photo) {
            // Explicit handling of missing data
            return BodyTagPictureResponseDTO::empty($photoId);
        }

        $shots = $this->pictureShots->generateShots($photo, $element);

        return new BodyTagPictureResponseDTO(
            id: $photoId,
            caption: $element->caption(),
            alternate: $element->alternate(),
            orientation: $element->orientation(),
            shots: $shots,
        );
    }

    public function supports(BodyElement $element): bool
    {
        return $element instanceof BodyTagPicture;
    }
}
```

### 4.7 Updated BodyTagInsertedNewsDataTransformer

```php
final class BodyTagInsertedNewsDataTransformer implements BodyElementDataTransformerInterface
{
    public function transform(
        BodyElement $element,
        TransformContextDTO $context,
    ): BodyTagInsertedNewsResponseDTO {

        /** @var BodyTagInsertedNews $element */
        $editorialId = $element->editorialId()->id();

        // ✅ Typed access
        $insertedNews = $context->getInsertedNews($editorialId);

        if (null === $insertedNews) {
            throw new InsertedNewsNotFoundException($editorialId);
        }

        // ✅ Typed access to multimedia
        $multimedia = $context->getMultimedia($insertedNews->multimediaId);
        $shots = $multimedia ? $this->generateShots($multimedia) : ShotsDTO::empty();

        return new BodyTagInsertedNewsResponseDTO(
            editorialId: $editorialId,
            title: $insertedNews->editorial->title(),
            signatures: $insertedNews->signatures,
            shots: $shots,
            url: $this->urlGenerator->editorial($insertedNews->editorial, $insertedNews->section),
        );
    }
}
```

---

## 5. Updated Orchestrator Flow

```php
final class EditorialOrchestrator implements EditorialOrchestratorInterface
{
    public function execute(Request $request): array
    {
        $id = $request->attributes->get('id');

        // 1. Fetch main editorial (sync - needed for body analysis)
        $fetchedEditorial = $this->editorialFetcher->fetch($id);
        $editorial = $fetchedEditorial->editorial;
        $section = $fetchedEditorial->section;

        // 2. Collect body element data requirements (creates promises)
        $bodyRequirements = $this->bodyElementDataCollector->collect(
            $editorial->body()
        );

        // 3. Fetch embedded content (creates promises)
        $embeddedContent = $this->embeddedContentFetcher->fetch(
            $editorial,
            $section
        );

        // 4. Fetch membership (creates promise)
        $membershipPromise = $this->membershipFetcher->fetch($editorial, $section);

        // 5. Fetch tags (sync - small data)
        $tags = $this->tagFetcher->fetch($editorial);

        // 6. RESOLVE ALL PROMISES IN SINGLE WAIT
        $resolvedData = $this->promiseResolver->resolveAll(
            multimediaPromises: $embeddedContent->multimediaPromises,
            membershipPromises: [$membershipPromise],
            bodyRequirements: $bodyRequirements,
        );

        // 7. Aggregate response
        return $this->responseAggregator->aggregate(
            fetchedEditorial: $fetchedEditorial,
            embeddedContent: $embeddedContent,
            tags: $tags,
            resolvedData: $resolvedData,  // ← Single typed DTO
        );
    }
}
```

---

## 6. Performance Comparison

### Before (Current)

```
Timeline:
0ms   ─── Editorial fetch (sync) ───────────────────> 50ms
50ms  ─── Embedded content fetch (sync) ────────────> 100ms
100ms ─── Photo 1 fetch (sync) ─────────────────────> 150ms
150ms ─── Photo 2 fetch (sync) ─────────────────────> 200ms
200ms ─── Photo 3 fetch (sync) ─────────────────────> 250ms
...
450ms ─── Multimedia promises resolve ──────────────> 500ms
500ms ─── Response aggregation ─────────────────────> 510ms

TOTAL: ~510ms (with 5 photos)
```

### After (Proposed)

```
Timeline:
0ms   ─── Editorial fetch (sync) ───────────────────> 50ms
50ms  ─── Collect requirements (no HTTP) ───────────> 51ms
51ms  ─┬─ Photo 1 promise (async) ─────────────────────────┐
      ├─ Photo 2 promise (async) ─────────────────────────┤
      ├─ Photo 3 promise (async) ─────────────────────────┤
      ├─ Multimedia promises (async) ─────────────────────┤
      └─ Membership promise (async) ───────────────────────┤
                                                           │
100ms ─── All promises resolved (parallel) ───────────────┘
100ms ─── Response aggregation ─────────────────────> 110ms

TOTAL: ~110ms (with 5 photos)
```

**Improvement**: ~400ms saved (78% faster) for editorial with 5 body tag photos.

---

## 7. Implementation Phases

### Phase 1: Extract BodyElementDataCollector

**Goal**: Move photo fetching to collector, keep sync for now

| Task | Risk |
|------|------|
| Create `BodyElementDataCollector` class | LOW |
| Move `retrievePhotosFromBodyTags()` logic | LOW |
| Update `EditorialOrchestrator` to use collector | MEDIUM |
| Maintain backward compatibility | LOW |

**Deliverable**: Same behavior, better separation

### Phase 2: Make Photo Fetching Async

**Goal**: Photos fetched as promises

| Task | Risk |
|------|------|
| Add `async: true` to photo client calls | LOW |
| Return promises from collector | LOW |
| Add photos to promise resolution | MEDIUM |
| Update tests | MEDIUM |

**Deliverable**: Photos fetched in parallel

### Phase 3: Typed Context DTO

**Goal**: Replace `array<string, mixed>` with `TransformContextDTO`

| Task | Risk |
|------|------|
| Create `TransformContextDTO` | LOW |
| Create typed collections | LOW |
| Update `ResponseAggregator` | MEDIUM |
| Update all transformers | HIGH |

**Deliverable**: Full type safety

### Phase 4: Typed Response DTOs

**Goal**: Transformers return DTOs instead of arrays

| Task | Risk |
|------|------|
| Create `BodyTagPictureResponseDTO` etc. | LOW |
| Update transformer interface | LOW |
| Update all transformers | HIGH |
| Update tests | MEDIUM |

**Deliverable**: Complete type safety chain

---

## 8. Migration Strategy

### Strangler Fig for Transformers

```php
// Phase 1: Add new interface alongside old
interface BodyElementDataTransformerInterface
{
    // OLD (keep for now)
    public function write(BodyElement $element, array $resolveData): self;
    public function read(): array;

    // NEW (add)
    public function transform(
        BodyElement $element,
        TransformContextDTO $context
    ): BodyElementResponseDTO;
}

// Phase 2: Transformers implement both
class BodyTagPictureDataTransformer implements BodyElementDataTransformerInterface
{
    // OLD - delegates to new
    public function write(BodyElement $element, array $resolveData): self
    {
        $this->element = $element;
        $this->context = TransformContextDTO::fromArray($resolveData);
        return $this;
    }

    public function read(): array
    {
        return $this->transform($this->element, $this->context)->toArray();
    }

    // NEW - actual implementation
    public function transform(
        BodyElement $element,
        TransformContextDTO $context
    ): BodyTagPictureResponseDTO {
        // ... typed implementation
    }
}

// Phase 3: Update callers to use transform() directly
// Phase 4: Remove old methods
```

---

## 9. Success Metrics

| Metric | Before | After |
|--------|--------|-------|
| Photo fetch time (5 photos) | ~250ms | ~50ms |
| Type safety | 0% | 100% |
| Magic key access | ~20 places | 0 |
| Testability | Hard (mocking arrays) | Easy (mocking DTOs) |
| IDE support | Minimal | Full |

---

## 10. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Breaking existing tests | HIGH | Strangler fig pattern |
| Performance regression | MEDIUM | Benchmark before/after |
| Incomplete migration | LOW | Feature flags |
| Client doesn't support async photos | HIGH | Check `QueryMultimediaClient` first |

---

## 11. Questions to Resolve

1. **Does `QueryMultimediaClient::findPhotoById()` support async?**
   - If not, need to add support first

2. **Are there cache implications?**
   - Parallel requests might hit cache differently

3. **Error handling strategy?**
   - What if one photo fails but others succeed?

4. **Priority**: Start with Phase 1 or jump to Phase 2?
