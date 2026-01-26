# JSON Response Layer Analysis

**Project**: SNAAPI
**Date**: 2026-01-26
**Focus**: Layer 2 improvements and best practices

---

## 1. Current State Analysis

### DataTransformer Inventory

| Category | Count | Purpose |
|----------|-------|---------|
| Body Elements | 11 | Paragraphs, lists, media in body |
| Media | 6 | Photos, videos, widgets |
| Apps (General) | 14 | Editorial, section, signatures |
| **Total** | 31 | |

### Current Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  ResponseAggregator                         │
│                                                             │
│  aggregate($editorial, $section, $tags, $multimedia, ...)  │
└─────────────────────────┬───────────────────────────────────┘
                          │
    ┌─────────────────────┼─────────────────────┐
    │                     │                     │
    ▼                     ▼                     ▼
┌────────────┐    ┌────────────────┐    ┌──────────────────┐
│ AppsData   │    │ BodyData       │    │ MultimediaData   │
│ Transformer│    │ Transformer    │    │ Transformer      │
└────────────┘    └───────┬────────┘    └──────────────────┘
                          │
         ┌────────────────┼────────────────┐
         │                │                │
         ▼                ▼                ▼
    ┌─────────┐    ┌───────────┐    ┌────────────┐
    │Paragraph│    │BodyTag    │    │SubHead     │
    │Transform│    │Picture    │    │Transform   │
    └─────────┘    └───────────┘    └────────────┘
```

### Type Safety Issues

```php
// Current: Mixed types throughout
final readonly class EditorialResponseDTO
{
    public function __construct(
        public string $id,
        public TitlesDTO $titles,         // ✅ Typed
        public array $body,               // ❌ Should be BodyDTO
        public ?array $multimedia,        // ❌ Should be MultimediaDTO|null
        public array $standfirst,         // ❌ Should be StandfirstDTO
        public array $recommendedEditorials,  // ❌ Should be array<EditorialSummaryDTO>
    ) {}
}
```

---

## 2. Key Question: Should HTTP Requests Be in Response Layer?

### Answer: NO

**Current Status**: ✅ COMPLIANT

The response layer (DataTransformers) does NOT make HTTP requests. All HTTP calls happen in:
- `EditorialOrchestrator` (Layer 1)
- `EditorialFetcher` (Layer 1)
- `EmbeddedContentFetcher` (Layer 1)
- `PromiseResolver` (Layer 1)

### Correct Flow

```
Layer 1: HTTP Reading
──────────────────────
EditorialOrchestrator
    │
    ├── editorialFetcher.fetch()        → HTTP call
    ├── embeddedContentFetcher.fetch()  → HTTP calls (promises)
    └── promiseResolver.resolve()       → Waits for promises
                │
                ▼
            Raw Data (Editorial, Multimedia, Tags objects)
                │
                ▼
Layer 2: JSON Response
──────────────────────
ResponseAggregator
    │
    ├── appsDataTransformer.transform()    → No HTTP
    ├── bodyDataTransformer.transform()    → No HTTP
    └── multimediaDataTransformer.transform() → No HTTP
                │
                ▼
            JSON Response (array)
```

### Exception Analysis: Thumbor

```php
// In DetailsMultimediaPhotoDataTransformer
public function __construct(private readonly Thumbor $thumborService) {}

public function read(): array
{
    // This DOES NOT make HTTP calls
    // Thumbor only BUILDS URLs for image transformation
    $url = $this->thumborService->retriveCropBodyTagPicture(
        $resource->file(),
        $width,
        $height,
        ...
    );
    // Returns: https://thumbor.example.com/unsafe/300x200/image.jpg
}
```

**Verdict**: Thumbor is URL construction, not HTTP requests. However, it IS an infrastructure concern in the application layer.

---

## 3. Identified Violations

### Violation 1: Infrastructure in Application Layer

**Location**: `JournalistsDataTransformer`, `DetailsMultimediaPhotoDataTransformer`

```php
// Application Layer should not depend on Infrastructure
class JournalistsDataTransformer
{
    public function __construct(
        private readonly Thumbor $thumbor  // ❌ Infrastructure dependency
    ) {}
}
```

**Impact**:
- Violates Dependency Inversion Principle
- Harder to unit test (need to mock Thumbor)
- Couples transformation logic to image service

### Violation 2: Incomplete DTOs

**Location**: `EditorialResponseDTO`

```php
// Some fields are typed DTOs, others are raw arrays
public array $body;        // ❌ Should be BodyDTO
public ?array $multimedia; // ❌ Should be MultimediaDTO|null
```

**Impact**:
- No type safety for nested structures
- IDE can't help with autocomplete
- Easier to introduce bugs

### Violation 3: Missing Response Envelope

**Current Response**:
```json
{
  "id": "123",
  "title": "...",
  "body": [...]
}
```

**REST Best Practice**:
```json
{
  "data": {
    "id": "123",
    "type": "editorial",
    "attributes": {
      "title": "...",
      "body": [...]
    }
  },
  "meta": {
    "requestId": "abc-123",
    "cached": true
  }
}
```

---

## 4. Improvement Proposals

### Proposal 1: Extract Image URL Building

**Current**:
```php
class JournalistsDataTransformer
{
    public function __construct(private readonly Thumbor $thumbor) {}

    private function photoUrl(Journalist $journalist): string
    {
        return $this->thumbor->createJournalistImage($journalist->blogPhoto());
    }
}
```

**Proposed**:
```php
// Option A: Interface in Application, Implementation in Infrastructure
interface ImageUrlBuilderInterface
{
    public function buildJournalistUrl(string $photo): string;
    public function buildMultimediaUrl(string $file, int $width, int $height): string;
}

class ThumborImageUrlBuilder implements ImageUrlBuilderInterface
{
    public function __construct(private readonly Thumbor $thumbor) {}

    public function buildJournalistUrl(string $photo): string
    {
        return $this->thumbor->createJournalistImage($photo);
    }
}

// Transformer now depends on abstraction
class JournalistsDataTransformer
{
    public function __construct(private readonly ImageUrlBuilderInterface $imageBuilder) {}
}
```

**Benefits**:
- Dependency Inversion respected
- Easier to test (mock interface)
- Can swap Thumbor for another service

### Proposal 2: Complete DTO Hierarchy

```php
// Body DTOs
final readonly class BodyDTO
{
    public function __construct(
        public string $type,
        /** @var BodyElementDTO[] */
        public array $elements,
    ) {}
}

abstract readonly class BodyElementDTO
{
    public function __construct(
        public string $type,
    ) {}
}

final readonly class ParagraphDTO extends BodyElementDTO
{
    public function __construct(
        public string $content,
        /** @var LinkDTO[]|null */
        public ?array $links,
    ) {
        parent::__construct('paragraph');
    }
}

final readonly class BodyTagPictureDTO extends BodyElementDTO
{
    public function __construct(
        public string $id,
        public string $caption,
        public ShotsDTO $shots,
    ) {
        parent::__construct('picture');
    }
}

// Multimedia DTOs
final readonly class MultimediaDTO
{
    public function __construct(
        public string $id,
        public string $type,
        public ?string $caption,
        public ?ShotsDTO $shots,
    ) {}
}

final readonly class ShotsDTO
{
    public function __construct(
        public string $small,
        public string $medium,
        public string $large,
        public ?string $original,
    ) {}
}
```

**Benefits**:
- Full type safety
- IDE support
- Self-documenting code
- Easier refactoring

### Proposal 3: Response Envelope

```php
final readonly class ApiResponse
{
    public function __construct(
        public mixed $data,
        public ApiMeta $meta,
        public array $errors = [],
    ) {}

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'meta' => $this->meta->toArray(),
            'errors' => $this->errors,
        ];
    }
}

final readonly class ApiMeta
{
    public function __construct(
        public string $requestId,
        public bool $cached,
        public ?string $cacheKey = null,
    ) {}
}

// Usage in Controller
public function getEditorial(Request $request, string $id): JsonResponse
{
    $data = $this->orchestrator->execute($request);

    return new JsonResponse(
        new ApiResponse(
            data: $data,
            meta: new ApiMeta(
                requestId: $request->attributes->get('requestId'),
                cached: $this->cache->wasHit(),
            ),
        )->toArray()
    );
}
```

---

## 5. Implementation Priority

| Priority | Improvement | Effort | Impact |
|----------|-------------|--------|--------|
| 1 | Extract ImageUrlBuilder interface | Low | High (DI compliance) |
| 2 | Create missing DTOs (Body, Multimedia) | Medium | High (type safety) |
| 3 | Response envelope | Low | Medium (API consistency) |
| 4 | Split EditorialOrchestrator | High | High (maintainability) |

### Phase 1: Quick Wins (1-2 days)

1. Create `ImageUrlBuilderInterface`
2. Implement `ThumborImageUrlBuilder`
3. Update DI configuration
4. Update transformers to use interface

### Phase 2: Type Safety (3-5 days)

1. Create DTO hierarchy for Body elements
2. Create DTO for Multimedia
3. Update transformers to return DTOs
4. Update tests

### Phase 3: API Consistency (1 day)

1. Create `ApiResponse` envelope
2. Create `ApiMeta` class
3. Update controllers

---

## 6. Summary

### What's Working Well

- ✅ Clear separation: HTTP in Layer 1, Transform in Layer 2
- ✅ DataTransformers don't make HTTP calls
- ✅ Strategy pattern with compiler passes
- ✅ Chain of responsibility for type routing

### What Needs Improvement

- ⚠️ Thumbor (Infrastructure) in Application layer
- ⚠️ Incomplete DTOs (mixed with arrays)
- ⚠️ No standard response envelope
- ⚠️ EditorialOrchestrator too large

### Key Specification

```
SPEC-JSON-001: Response Layer Responsibilities
- DataTransformers MUST NOT make HTTP requests
- DataTransformers MUST only transform data
- All HTTP calls MUST happen in Layer 1 (Orchestrator/Fetcher)
- Infrastructure services (Thumbor) SHOULD be accessed via interfaces

SPEC-JSON-002: Type Safety
- Response DTOs SHOULD be fully typed
- Avoid array<string, mixed> where possible
- Use readonly classes for immutability

SPEC-JSON-003: Response Format
- All responses SHOULD follow envelope pattern
- Include meta information (requestId, cache status)
- Errors SHOULD be structured consistently
```
