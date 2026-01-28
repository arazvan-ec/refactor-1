# Tasks: snaapi-pragmatic-refactor

**Feature**: snaapi-pragmatic-refactor
**Created**: 2026-01-27
**Methodology**: Incremental with PHPStan validation
**Max Iterations per Task**: 5

---

## Task BE-001: Add PHPDoc Array Shapes to Orchestrator

**Role**: Backend Engineer
**Priority**: P1
**Estimated Time**: 45 minutes
**Trust Level**: 🟢 LOW

### Objective
Add precise PHPDoc array shapes to `EditorialOrchestrator::execute()` method.

### File
`src/Orchestrator/Chain/EditorialOrchestrator.php:54-61`

### Current State
```php
/**
 * Execute the editorial orchestration.
 *
 * @return array<string, mixed>
 *
 * @throws \Throwable
 */
public function execute(Request $request): array
```

### Target State
```php
/**
 * Execute the editorial orchestration.
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
 *   body: list<array{type: string, content?: string, ...}>,
 *   multimedia: array{id: string, type: string, caption: string, shots: object, photo: string}|null,
 *   standfirst: list<array{type: string, content: string}>,
 *   recommendedEditorials: list<array{id: string, title: string, url: string, image: string}>
 * }
 *
 * @throws \Throwable
 */
public function execute(Request $request): array
```

### Steps
1. Read `ResponseAggregator::aggregate()` to understand exact structure
2. Read `EditorialResponseDTO::fromArray()` for field reference
3. Add PHPDoc array shape to `execute()` method
4. Run `make test_stan`
5. Verify IDE autocomplete works

### Verification
```bash
make test_stan
```

### Acceptance Criteria
- [ ] PHPDoc array shape added with all fields
- [ ] `make test_stan` passes
- [ ] No behavior changes (annotation only)

### Notes
- Reference `EditorialResponseDTO` for field names
- Use `list<>` for indexed arrays, `array{}` for associative
- Use `...` for complex nested structures that vary

---

## Task BE-002: Add PHPDoc Array Shapes to ResponseAggregator

**Role**: Backend Engineer
**Priority**: P1
**Estimated Time**: 30 minutes
**Trust Level**: 🟢 LOW

### Objective
Add precise PHPDoc array shapes to `ResponseAggregator::aggregate()` and helper methods.

### File
`src/Application/Service/Editorial/ResponseAggregator.php`

### Methods to Annotate

#### aggregate() - Line 57
```php
/**
 * Aggregate all fetched data into final editorial response.
 *
 * @param array<int, Tag> $tags
 * @param array<string, mixed> $resolvedMultimedia
 * @param array<string, string> $membershipLinks
 * @param array<string, mixed> $photoBodyTags
 *
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
 *   countComments: int,
 *   signatures: list<array{id: string, name: string, picture: string|null, url: string}>,
 *   body: list<array{type: string, ...}>,
 *   multimedia: array{id: string, type: string, caption: string, shots: object}|null,
 *   standfirst: list<array{type: string, content: string}>,
 *   recommendedEditorials: list<array{id: string, title: string, url: string}>
 * }
 */
public function aggregate(...): array
```

#### buildResolveData() - Line 174
```php
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
 *   membershipLinkCombine: array<string, string>,
 *   photoFromBodyTags: array<string, mixed>
 * }
 */
private function buildResolveData(...): array
```

### Steps
1. Add PHPDoc to `aggregate()` method
2. Add PHPDoc to `buildResolveData()` method
3. Run `make test_stan`

### Verification
```bash
make test_stan
```

### Acceptance Criteria
- [ ] PHPDoc array shapes added to both methods
- [ ] `make test_stan` passes
- [ ] No behavior changes

---

## Task BE-003: Extract SIZES_RELATIONS to Config Class

**Role**: Backend Engineer
**Priority**: P1
**Estimated Time**: 1 hour
**Trust Level**: 🟢 LOW

### Objective
Create centralized config class for multimedia image sizes, eliminating ~690 lines of duplication.

### Files

#### Create New File
`src/Infrastructure/Config/MultimediaImageSizes.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

/**
 * Centralized configuration for multimedia image sizes.
 *
 * Consolidates SIZES_RELATIONS constants previously duplicated across:
 * - DetailsMultimediaPhotoDataTransformer
 * - DetailsMultimediaDataTransformer
 * - PictureShots
 */
final class MultimediaImageSizes
{
    public const WIDTH = 'width';
    public const HEIGHT = 'height';

    public const ASPECT_RATIO_16_9 = '16:9';
    public const ASPECT_RATIO_4_3 = '4:3';
    public const ASPECT_RATIO_3_4 = '3:4';
    public const ASPECT_RATIO_3_2 = '3:2';
    public const ASPECT_RATIO_2_3 = '2:3';

    /** @var array<string, array<string, array{width: string, height: string}>> */
    public const SIZES_RELATIONS = [
        // Copy from DetailsMultimediaPhotoDataTransformer
    ];

    /**
     * Get sizes for a specific aspect ratio.
     *
     * @return array<string, array{width: string, height: string}>|null
     */
    public static function forAspectRatio(string $aspectRatio): ?array
    {
        return self::SIZES_RELATIONS[$aspectRatio] ?? null;
    }

    /**
     * Get all available aspect ratios.
     *
     * @return list<string>
     */
    public static function aspectRatios(): array
    {
        return array_keys(self::SIZES_RELATIONS);
    }
}
```

#### Modify Files
1. `src/Application/DataTransformer/Apps/Media/DataTransformers/DetailsMultimediaPhotoDataTransformer.php`
   - Remove local `SIZES_RELATIONS` constant (~230 lines)
   - Import `MultimediaImageSizes`
   - Replace `self::SIZES_RELATIONS` with `MultimediaImageSizes::SIZES_RELATIONS`
   - Replace `self::WIDTH/HEIGHT` with `MultimediaImageSizes::WIDTH/HEIGHT`
   - Replace aspect ratio constants

2. `src/Application/DataTransformer/Apps/DetailsMultimediaDataTransformer.php`
   - Same changes as above

3. `src/Infrastructure/Service/PictureShots.php`
   - Same changes as above

### TDD Approach
1. 🔴 RED: Write test for `MultimediaImageSizes::forAspectRatio()`
2. 🟢 GREEN: Create config class with constants
3. 🔵 REFACTOR: Update first transformer
4. Run tests
5. 🔵 REFACTOR: Update remaining files
6. Run full test suite

### Test to Write
```php
// tests/Unit/Infrastructure/Config/MultimediaImageSizesTest.php

public function test_for_aspect_ratio_returns_sizes(): void
{
    $sizes = MultimediaImageSizes::forAspectRatio(MultimediaImageSizes::ASPECT_RATIO_16_9);

    self::assertIsArray($sizes);
    self::assertArrayHasKey('1440w', $sizes);
    self::assertSame('1440', $sizes['1440w']['width']);
    self::assertSame('810', $sizes['1440w']['height']);
}

public function test_for_aspect_ratio_returns_null_for_unknown(): void
{
    $sizes = MultimediaImageSizes::forAspectRatio('unknown');

    self::assertNull($sizes);
}

public function test_aspect_ratios_returns_all_keys(): void
{
    $ratios = MultimediaImageSizes::aspectRatios();

    self::assertContains('16:9', $ratios);
    self::assertContains('4:3', $ratios);
    self::assertContains('3:4', $ratios);
    self::assertContains('3:2', $ratios);
    self::assertContains('2:3', $ratios);
}
```

### Verification
```bash
./bin/phpunit tests/Unit/Infrastructure/Config/MultimediaImageSizesTest.php
./bin/phpunit tests/Unit/Application/DataTransformer/
make test_stan
```

### Acceptance Criteria
- [ ] `MultimediaImageSizes` config class created
- [ ] Unit tests for config class passing
- [ ] All 3 files updated to use config class
- [ ] ~690 lines of duplication removed
- [ ] `make test_stan` passes
- [ ] `make test_unit` passes
- [ ] Behavior unchanged (existing tests pass)

---

## Task BE-004: Refactor ExceptionSubscriber

**Role**: Backend Engineer
**Priority**: P2
**Estimated Time**: 45 minutes
**Trust Level**: 🟢 LOW

### Objective
Improve readability by extracting to private methods. No new classes.

### File
`src/EventSubscriber/ExceptionSubscriber.php` (165 lines)

### Current Structure
- `onKernelException()` - Main handler with inline logic
- Mixed concerns: error classification, logging, response building

### Target Structure
```php
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

    if ($exception instanceof HttpExceptionInterface) {
        return $this->createHttpExceptionResponse($exception);
    }

    return $this->createGenericErrorResponse($exception);
}

private function createDomainExceptionResponse(DomainExceptionInterface $exception): JsonResponse
{
    return new JsonResponse([
        'error' => $exception->getErrorCode(),
        'message' => $exception->getMessage(),
        'context' => $exception->getContext(),
    ], $exception->getHttpStatusCode());
}

private function createHttpExceptionResponse(HttpExceptionInterface $exception): JsonResponse
{
    // ...
}

private function createGenericErrorResponse(\Throwable $exception): JsonResponse
{
    // ...
}

private function logException(\Throwable $exception): void
{
    // Centralized logging with appropriate level
}
```

### Steps
1. Read current ExceptionSubscriber implementation
2. Identify distinct responsibilities
3. Extract to private methods (no new classes)
4. Run tests
5. Verify error responses unchanged

### Verification
```bash
./bin/phpunit tests/Unit/EventSubscriber/ExceptionSubscriberTest.php
make test_unit
```

### Acceptance Criteria
- [ ] Main handler delegated to private methods
- [ ] No new classes created
- [ ] All existing tests pass
- [ ] Error response format unchanged
- [ ] Lines reduced from ~165 to ~120

---

## Execution Order

```
BE-001 (PHPDoc Orchestrator)
    ↓
BE-002 (PHPDoc ResponseAggregator)
    ↓
BE-003 (Extract SIZES_RELATIONS) ← Highest value, most impact
    ↓
BE-004 (ExceptionSubscriber cleanup)
```

### Parallel Execution
Tasks BE-001 and BE-002 can run in parallel (independent PHPDoc changes).
Tasks BE-003 and BE-004 must be sequential (test suite between each).

---

## Estimated Total Time

| Task | Time | Cumulative |
|------|------|------------|
| BE-001 | 45 min | 45 min |
| BE-002 | 30 min | 1h 15m |
| BE-003 | 1h | 2h 15m |
| BE-004 | 45 min | 3h |

**Total**: ~3 hours (vs 40+ hours for full specs)

---

## Escape Hatches

If blocked on any task after 5 iterations:
1. Document blocker in `DECISIONS.md`
2. Skip to next task
3. Return to blocked task with fresh context
