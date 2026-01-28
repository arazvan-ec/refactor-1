# Simplified Action Plan for SNAAPI Refactor

**Status**: PROPOSED
**Approach**: Agent-Optimized (Easy to Execute, High Value)
**Total Estimated Time**: 4-6 hours

---

## Why This Plan is Different

Traditional specs focus on **architectural purity**. This plan focuses on:
1. **What's easy for an AI agent to execute correctly**
2. **What delivers measurable value**
3. **What minimizes risk of introducing bugs**

---

## Action 1: Add PHPDoc Array Shapes (2 hours)

### What
Add precise type annotations to key methods without creating new classes.

### Why
- PHPStan validates at Level 9
- IDE support improves
- Zero runtime risk
- Fully reversible

### Files to Modify

```
src/Orchestrator/Chain/EditorialOrchestrator.php
src/Application/Service/Editorial/ResponseAggregator.php
src/Application/Service/Editorial/EmbeddedContentFetcher.php
```

### Example Change

```php
// ResponseAggregator.php - BEFORE
/**
 * @return array<string, mixed>
 */
public function aggregate(...): array

// ResponseAggregator.php - AFTER
/**
 * @return array{
 *   id: string,
 *   url: string,
 *   titles: array{title: string, preTitle: string, urlTitle: string, mobileTitle: string},
 *   lead: string,
 *   publicationDate: string,
 *   updatedOn: string,
 *   type: array{id: string, name: string},
 *   section: array{id: string, name: string, url: string, encodeName: string},
 *   tags: list<array{id: string, name: string, url: string}>,
 *   signatures: list<array{id: string, name: string, picture: string|null}>,
 *   body: list<array{type: string, content?: string, ...}>,
 *   multimedia: array{id: string, type: string, pictures: array<string, mixed>}|null,
 *   standfirst: list<array{type: string, content: string}>,
 *   recommendedEditorials: list<array{id: string, title: string, url: string, ...}>
 * }
 */
public function aggregate(...): array
```

### Verification
```bash
make test_stan
```

### Risk: LOW
- Annotations only, no behavior change
- PHPStan will validate correctness
- Tests remain unchanged

---

## Action 2: Extract Image Sizes Config (1 hour)

### What
Create single source of truth for multimedia image size configurations.

### Why
- Removes ~200 lines of duplication
- Single place to update sizes
- Clear ROI

### Files to Create
```
src/Infrastructure/Config/MultimediaImageSizes.php
```

### Files to Modify
```
src/Application/DataTransformer/Apps/Media/DataTransformers/DetailsMultimediaPhotoDataTransformer.php
src/Application/DataTransformer/Apps/Media/DataTransformers/DetailsMultimediaDataTransformer.php
```

### Implementation

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

/**
 * Centralized configuration for multimedia image sizes.
 *
 * Replaces duplicated SIZES_RELATIONS constants across DataTransformers.
 */
final class MultimediaImageSizes
{
    /**
     * Size relations for different display contexts.
     * Key = context name, Value = [desktop, mobile] dimensions.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const SIZES_RELATIONS = [
        'teaser' => ['660x371', '375x211'],
        'opening' => ['1200x675', '750x422'],
        'default' => ['980x551', '640x360'],
        // ... complete list from existing code
    ];

    /**
     * Get sizes for a specific context.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function forContext(string $context): ?array
    {
        return self::SIZES_RELATIONS[$context] ?? null;
    }

    /**
     * Get all available contexts.
     *
     * @return list<string>
     */
    public static function contexts(): array
    {
        return array_keys(self::SIZES_RELATIONS);
    }
}
```

### Verification
```bash
./bin/phpunit tests/Unit/Application/DataTransformer/
make test_stan
```

### Risk: LOW
- Extracting constants, not changing logic
- Tests validate behavior unchanged

---

## Action 3: Simplify Exception Handling (1 hour)

### What
Clean up ExceptionSubscriber without adding new abstraction layers.

### Why
- Current file is 165 lines with mixed responsibilities
- Can be simplified without new classes

### Approach
- Extract error response building to private methods
- Add PHPDoc for error response structure
- Remove redundant logging

### Files to Modify
```
src/EventSubscriber/ExceptionSubscriber.php
```

### Example
```php
// BEFORE: Mixed inline logic
public function onKernelException(ExceptionEvent $event): void
{
    $exception = $event->getThrowable();

    if ($exception instanceof DomainExceptionInterface) {
        $response = new JsonResponse([
            'error' => $exception->getErrorCode(),
            'message' => $exception->getMessage(),
            'context' => $exception->getContext(),
        ], $exception->getHttpStatusCode());
        // ... more inline logic
    }
}

// AFTER: Extracted to clear methods
public function onKernelException(ExceptionEvent $event): void
{
    $exception = $event->getThrowable();

    $response = $this->createErrorResponse($exception);
    $this->logException($exception);

    $event->setResponse($response);
}

private function createErrorResponse(\Throwable $exception): JsonResponse
{
    if ($exception instanceof DomainExceptionInterface) {
        return $this->createDomainExceptionResponse($exception);
    }

    return $this->createGenericErrorResponse($exception);
}
```

### Risk: LOW
- Refactoring within single file
- Behavior unchanged
- Tests validate

---

## Action 4: (Optional) Batch Photo Fetching

### What
Add batch method if measurements show photo fetching is slow.

### Prerequisites
- Measure actual latency first
- Confirm N+1 is a real problem

### Files to Modify
```
src/Orchestrator/Chain/EditorialOrchestrator.php
```

### Simple Implementation
```php
private function retrievePhotosFromBodyTags(Body $body): array
{
    $bodyTagPictures = $body->bodyElementsOf(BodyTagPicture::class);

    if (empty($bodyTagPictures)) {
        return [];
    }

    // Collect all IDs first
    $photoIds = array_map(
        fn(BodyTagPicture $tag) => $tag->pictureId()->id(),
        $bodyTagPictures
    );

    // Single batch call (if QueryMultimediaClient supports it)
    // Otherwise, use promise-based parallel calls
    return $this->queryMultimediaClient->findPhotosByIds($photoIds);
}
```

### Risk: MEDIUM
- Depends on client capabilities
- Only do if measured latency is >200ms

---

## What NOT To Do

### Don't Create These (From Original Specs)

| Proposed Class | Why Skip |
|----------------|----------|
| EditorialAggregateDTO | PHPDoc achieves same type safety |
| TransformContextDTO | Just pass array with documented shape |
| BodyElementResponseDTO hierarchy | Over-engineering for simple data |
| MultimediaCollection | PHP arrays work fine |
| 15+ additional DTOs | Maintenance burden > benefit |

### Don't Add These Abstractions

- PhotoBodyTagFetcher service (premature)
- PromiseBatcher class (existing Utils::settle works)
- ErrorResponseBuilder (extract to methods instead)

---

## Success Criteria

After implementing Actions 1-3:

- [ ] `make test_stan` passes (Level 9)
- [ ] `make test_unit` passes
- [ ] No new files except MultimediaImageSizes.php
- [ ] Lines of code: neutral or decreased
- [ ] Developer experience: improved (IDE support)

---

## Summary

| Action | New Files | Time | Value |
|--------|-----------|------|-------|
| PHPDoc Array Shapes | 0 | 2h | HIGH |
| Image Sizes Config | 1 | 1h | HIGH |
| Exception Cleanup | 0 | 1h | MEDIUM |
| Batch Photos | 0 | 1h | DEFER |

**Total: 1 new file, 4-5 hours, meaningful improvement.**

Compare to original specs: 25+ files, 40+ hours, questionable ROI.

---

## For The Agent

When executing this plan:

1. **Start with Action 1** - Safest, highest value
2. **Run tests frequently** - After each method change
3. **Commit incrementally** - Small, reversible changes
4. **Skip Action 4** - Unless user provides latency data
5. **Don't scope creep** - Resist adding "nice to have" changes
