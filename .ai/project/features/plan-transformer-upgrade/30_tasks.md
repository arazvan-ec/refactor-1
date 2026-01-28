# Tasks: DataTransformers Architecture Upgrade

**Feature ID**: plan-transformer-upgrade
**Created**: 2026-01-27

---

## Phase 1: Create Typed DTOs (Foundation)

### Task 1.1: Create ResolveDataDTO
**File**: `src/Application/DataTransformer/DTO/ResolveDataDTO.php`
**Complexity**: LOW
**Rationale**: Replace `array<string, mixed>` with typed structure

```php
<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\DTO;

/**
 * Data required for body element transformation.
 * Replaces array<string, mixed> $resolveData parameter.
 */
final readonly class ResolveDataDTO
{
    /**
     * @param array<string, Photo> $photoBodyTags Photo data indexed by multimedia ID
     * @param array<string, string>|null $membershipLinks URL mappings for membership
     * @param array<string, array<string, mixed>> $multimedia Resolved multimedia data
     * @param array<string, array<string, mixed>> $insertedEditorials Pre-fetched inserted editorials
     * @param array<string, array<string, mixed>> $recommendedEditorials Pre-fetched recommended
     */
    public function __construct(
        public array $photoBodyTags = [],
        public ?array $membershipLinks = null,
        public array $multimedia = [],
        public array $insertedEditorials = [],
        public array $recommendedEditorials = [],
    ) {}

    public function hasPhotoForId(string $id): bool
    {
        return isset($this->photoBodyTags[$id]);
    }

    public function getPhotoById(string $id): ?Photo
    {
        return $this->photoBodyTags[$id] ?? null;
    }

    public function hasMembershipLink(string $url): bool
    {
        return $this->membershipLinks !== null
            && isset($this->membershipLinks[$url]);
    }

    public function getMembershipLink(string $url): ?string
    {
        return $this->membershipLinks[$url] ?? null;
    }
}
```

**Tests**: `tests/Unit/Application/DataTransformer/DTO/ResolveDataDTOTest.php`

---

### Task 1.2: Create TransformerOutputDTO
**File**: `src/Application/DataTransformer/DTO/TransformerOutputDTO.php`
**Complexity**: LOW
**Rationale**: Type the output of transformers

```php
<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\DTO;

/**
 * Standard output structure for body element transformers.
 */
final readonly class TransformerOutputDTO
{
    /**
     * @param string $type Body element type identifier
     * @param array<string, mixed> $data Transformed element data
     */
    public function __construct(
        public string $type,
        public array $data = [],
    ) {}

    /**
     * Convert to array for JSON serialization.
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(['type' => $this->type], $this->data);
    }
}
```

**Tests**: `tests/Unit/Application/DataTransformer/DTO/TransformerOutputDTOTest.php`

---

### Task 1.3: Create MultimediaShotDTO
**File**: `src/Application/DataTransformer/DTO/MultimediaShotDTO.php`
**Complexity**: LOW
**Rationale**: Type multimedia shot data structure

```php
<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\DTO;

/**
 * Represents a single multimedia shot with its URLs.
 */
final readonly class MultimediaShotDTO
{
    /**
     * @param string $size Size identifier (e.g., 'small', 'medium', 'large')
     * @param string $url Generated Thumbor URL
     * @param int $width Image width in pixels
     * @param int $height Image height in pixels
     */
    public function __construct(
        public string $size,
        public string $url,
        public int $width,
        public int $height,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'size' => $this->size,
            'url' => $this->url,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
```

**Tests**: `tests/Unit/Application/DataTransformer/DTO/MultimediaShotDTOTest.php`

---

### Task 1.4: Create MultimediaShotsCollectionDTO
**File**: `src/Application/DataTransformer/DTO/MultimediaShotsCollectionDTO.php`
**Complexity**: LOW
**Rationale**: Type collection of shots for a multimedia item

```php
<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\DTO;

/**
 * Collection of multimedia shots for different sizes.
 */
final readonly class MultimediaShotsCollectionDTO
{
    /**
     * @param array<MultimediaShotDTO> $shots
     */
    public function __construct(
        public array $shots = [],
    ) {}

    /**
     * @return array<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            fn(MultimediaShotDTO $shot) => $shot->toArray(),
            $this->shots
        );
    }

    public function isEmpty(): bool
    {
        return empty($this->shots);
    }

    public function count(): int
    {
        return count($this->shots);
    }
}
```

**Tests**: `tests/Unit/Application/DataTransformer/DTO/MultimediaShotsCollectionDTOTest.php`

---

## Phase 2: Extract Services (Eliminate Duplication)

### Task 2.1: Create MultimediaShotResolver Service
**File**: `src/Application/DataTransformer/Service/MultimediaShotResolver.php`
**Complexity**: MEDIUM
**Rationale**: Extract duplicated shot resolution logic from 4 transformers

**Current duplication locations**:
- `BodyTagInsertedNewsDataTransformer.php:76-82`
- `RecommendedEditorialsDataTransformer.php:81-85`
- `DetailsMultimediaPhotoDataTransformer.php:66-80`
- `DetailsMultimediaDataTransformer.php:60-74`

```php
<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\Service;

use App\Application\DataTransformer\DTO\MultimediaShotsCollectionDTO;
use App\Application\DataTransformer\DTO\ResolveDataDTO;

/**
 * Resolves multimedia shots from resolve data.
 * Centralizes logic previously duplicated across 4+ transformers.
 */
final class MultimediaShotResolver
{
    /**
     * Resolve shots for an editorial, preferring opening multimedia over body multimedia.
     *
     * @param string $editorialId The editorial ID
     * @param ResolveDataDTO $resolveData Pre-fetched resolve data
     * @return MultimediaShotsCollectionDTO Resolved shots
     */
    public function resolveForEditorial(
        string $editorialId,
        ResolveDataDTO $resolveData,
    ): MultimediaShotsCollectionDTO {
        // Prefer opening multimedia
        $openingShots = $this->getOpeningMultimediaShots($editorialId, $resolveData);
        if (!$openingShots->isEmpty()) {
            return $openingShots;
        }

        // Fallback to body multimedia
        return $this->getBodyMultimediaShots($editorialId, $resolveData);
    }

    private function getOpeningMultimediaShots(
        string $editorialId,
        ResolveDataDTO $resolveData,
    ): MultimediaShotsCollectionDTO {
        $opening = $resolveData->insertedEditorials[$editorialId]['opening'] ?? null;

        if ($opening === null) {
            return new MultimediaShotsCollectionDTO([]);
        }

        return $this->extractShotsFromMultimedia($opening);
    }

    private function getBodyMultimediaShots(
        string $editorialId,
        ResolveDataDTO $resolveData,
    ): MultimediaShotsCollectionDTO {
        $multimedia = $resolveData->multimedia[$editorialId] ?? null;

        if ($multimedia === null) {
            return new MultimediaShotsCollectionDTO([]);
        }

        return $this->extractShotsFromMultimedia($multimedia);
    }

    /**
     * @param array<string, mixed> $multimedia
     */
    private function extractShotsFromMultimedia(array $multimedia): MultimediaShotsCollectionDTO
    {
        $shots = $multimedia['shots'] ?? [];

        // Convert to DTOs
        // Implementation details based on current structure

        return new MultimediaShotsCollectionDTO($shots);
    }
}
```

**Tests**: `tests/Unit/Application/DataTransformer/Service/MultimediaShotResolverTest.php`

---

### Task 2.2: Create MultimediaShotGenerator Service
**File**: `src/Application/DataTransformer/Service/MultimediaShotGenerator.php`
**Complexity**: MEDIUM
**Rationale**: Convert `MultimediaTrait` to injectable service

**Current trait location**: `src/Infrastructure/Trait/MultimediaTrait.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\Service;

use App\Application\DataTransformer\DTO\MultimediaShotDTO;
use App\Application\DataTransformer\DTO\MultimediaShotsCollectionDTO;
use App\Infrastructure\Config\MultimediaImageSizes;
use App\Infrastructure\Service\Thumbor;

/**
 * Generates Thumbor shots for multimedia items.
 * Replaces MultimediaTrait with explicit dependency injection.
 */
final readonly class MultimediaShotGenerator
{
    public function __construct(
        private Thumbor $thumbor,
        private string $extension = 'webp',
    ) {}

    /**
     * Generate shots for all configured sizes.
     *
     * @param string $multimediaId The multimedia ID
     * @param string $originalUrl The original image URL
     * @return MultimediaShotsCollectionDTO Collection of generated shots
     */
    public function generateShots(
        string $multimediaId,
        string $originalUrl,
    ): MultimediaShotsCollectionDTO {
        $shots = [];

        foreach (MultimediaImageSizes::SIZES_RELATIONS as $size => $dimensions) {
            $shots[] = new MultimediaShotDTO(
                size: $size,
                url: $this->thumbor->generateUrl(
                    $originalUrl,
                    $dimensions['width'],
                    $dimensions['height'],
                    $this->extension,
                ),
                width: $dimensions['width'],
                height: $dimensions['height'],
            );
        }

        return new MultimediaShotsCollectionDTO($shots);
    }

    /**
     * Generate a single shot for specific dimensions.
     */
    public function generateSingleShot(
        string $originalUrl,
        int $width,
        int $height,
    ): MultimediaShotDTO {
        return new MultimediaShotDTO(
            size: 'custom',
            url: $this->thumbor->generateUrl(
                $originalUrl,
                $width,
                $height,
                $this->extension,
            ),
            width: $width,
            height: $height,
        );
    }
}
```

**Tests**: `tests/Unit/Application/DataTransformer/Service/MultimediaShotGeneratorTest.php`

---

## Phase 3: Standardize Interfaces

### Task 3.1: Create BodyElementTransformerInterface
**File**: `src/Application/DataTransformer/Contract/BodyElementTransformerInterface.php`
**Complexity**: LOW
**Rationale**: Rename and standardize existing interface

```php
<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\Contract;

use App\Application\DataTransformer\DTO\ResolveDataDTO;
use App\Application\DataTransformer\DTO\TransformerOutputDTO;
use Ec\Editorial\Domain\Editorial\Body\BodyElement;

/**
 * Contract for body element transformers.
 *
 * Implements Strategy pattern for transforming different body element types.
 */
interface BodyElementTransformerInterface
{
    /**
     * Write element data to transformer state (fluent interface).
     */
    public function write(BodyElement $bodyElement, ResolveDataDTO $resolveData): self;

    /**
     * Read transformed output.
     */
    public function read(): TransformerOutputDTO;

    /**
     * Get the fully qualified class name this transformer handles.
     */
    public function canTransform(): string;
}
```

**Migration**: Update `BodyElementDataTransformer` to extend this interface.

---

### Task 3.2: Update Handler to Use New Interface
**File**: `src/Application/DataTransformer/BodyElementDataTransformerHandler.php`
**Complexity**: LOW

```php
// Update method signature
public function addDataTransformer(BodyElementTransformerInterface $transformer): void

// Update execute to use ResolveDataDTO
public function execute(BodyElement $element, ResolveDataDTO $resolveData): TransformerOutputDTO
```

---

### Task 3.3: Create Backward Compatibility Adapter
**File**: `src/Application/DataTransformer/Adapter/LegacyResolveDataAdapter.php`
**Complexity**: LOW
**Rationale**: Allow gradual migration without breaking existing code

```php
<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\Adapter;

use App\Application\DataTransformer\DTO\ResolveDataDTO;

/**
 * Converts legacy array format to ResolveDataDTO.
 * Use during migration period, then remove.
 *
 * @deprecated Will be removed once all callers use ResolveDataDTO directly
 */
final class LegacyResolveDataAdapter
{
    /**
     * @param array<string, mixed> $legacyResolveData
     */
    public static function fromArray(array $legacyResolveData): ResolveDataDTO
    {
        return new ResolveDataDTO(
            photoBodyTags: $legacyResolveData['photoBodyTags'] ?? [],
            membershipLinks: $legacyResolveData['membershipLinks'] ?? null,
            multimedia: $legacyResolveData['multimedia'] ?? [],
            insertedEditorials: $legacyResolveData['insertedEditorials'] ?? [],
            recommendedEditorials: $legacyResolveData['recommendedEditorials'] ?? [],
        );
    }
}
```

---

## Phase 4: Refactor Long Transformers

### Task 4.1: Simplify DetailsAppsDataTransformer
**File**: `src/Application/DataTransformer/Apps/DetailsAppsDataTransformer.php`
**Complexity**: MEDIUM
**Current**: 211 lines
**Target**: <100 lines

**Strategy**:
1. Extract `transformerOptions()` logic to helper method
2. Inject `MultimediaShotGenerator` instead of using trait
3. Split `read()` into smaller methods

**Refactored structure**:
```php
final class DetailsAppsDataTransformer implements AppsDataTransformerInterface
{
    public function __construct(
        private readonly MultimediaShotGenerator $shotGenerator,
        private readonly EditorialUrlBuilder $urlBuilder,  // NEW: extracted
    ) {}

    public function read(): array
    {
        return [
            'id' => $this->buildId(),
            'type' => $this->buildType(),
            'attributes' => $this->buildAttributes(),
            'media' => $this->buildMedia(),
        ];
    }

    private function buildAttributes(): array { /* ... */ }
    private function buildMedia(): array { /* ... */ }
}
```

---

### Task 4.2: Simplify BodyTagInsertedNewsDataTransformer
**File**: `src/Application/DataTransformer/Apps/Body/BodyTagInsertedNewsDataTransformer.php`
**Complexity**: MEDIUM
**Current**: 143 lines
**Target**: <80 lines

**Strategy**:
1. Replace `MultimediaTrait` with `MultimediaShotResolver`
2. Remove duplicated shot resolution logic
3. Extract helper methods

---

### Task 4.3: Simplify RecommendedEditorialsDataTransformer
**File**: `src/Application/DataTransformer/Apps/RecommendedEditorialsDataTransformer.php`
**Complexity**: MEDIUM
**Current**: 170 lines
**Target**: <80 lines

**Strategy**: Same as Task 4.2 (similar structure, similar issues)

---

## Phase 5: Fix Inheritance Issues

### Task 5.1: Create ListDataTransformer Base
**File**: `src/Application/DataTransformer/Apps/Body/ListDataTransformer.php`
**Complexity**: MEDIUM
**Rationale**: Single base for all list transformers

```php
<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\Trait\LinksDataTransformerTrait;

/**
 * Base class for list transformers (ordered and unordered).
 */
abstract class ListDataTransformer extends ElementTypeDataTransformer
{
    use LinksDataTransformerTrait;

    protected function transformListItems(): array
    {
        // Common list transformation logic
    }

    protected function transformWithLinks(array $items): array
    {
        // Common link transformation logic
    }
}
```

---

### Task 5.2: Refactor UnorderedListDataTransformer
**File**: `src/Application/DataTransformer/Apps/Body/UnorderedListDataTransformer.php`
**Complexity**: LOW

```php
// Before: Dual inheritance confusion
class UnorderedListDataTransformer extends ElementContentWithLinksDataTransformer
// AND extends GenericListDataTransformer somehow

// After: Single clear inheritance
final class UnorderedListDataTransformer extends ListDataTransformer
{
    public function canTransform(): string
    {
        return UnorderedList::class;
    }

    public function read(): TransformerOutputDTO
    {
        $items = $this->transformListItems();
        $itemsWithLinks = $this->transformWithLinks($items);

        return new TransformerOutputDTO(
            type: 'unordered-list',
            data: ['items' => $itemsWithLinks],
        );
    }
}
```

---

### Task 5.3: Refactor NumberedListDataTransformer
**File**: `src/Application/DataTransformer/Apps/Body/NumberedListDataTransformer.php`
**Complexity**: LOW

Same pattern as Task 5.2.

---

## Phase 6: Architecture Tests & Validation

### Task 6.1: Add New Architecture Tests
**File**: `tests/Architecture/DataTransformerArchitectureTest.php`
**Complexity**: LOW

```php
public function testTransformersDoNotUseLegacyResolveDataArray(): void
{
    // Ensure no transformer uses array $resolveData parameter
}

public function testAllTransformersReturnTypedDTO(): void
{
    // Ensure all transformers return TransformerOutputDTO
}

public function testNoTransformerExceedsLineLimit(): void
{
    // Ensure no transformer > 100 lines
}

public function testSingleInheritancePath(): void
{
    // Ensure no class has multiple inheritance paths
}
```

---

### Task 6.2: Add Regression Tests
**File**: `tests/Integration/DataTransformer/TransformerRegressionTest.php`
**Complexity**: MEDIUM

Compare output before/after refactoring:
1. Same input produces same output structure
2. All fields present
3. No type changes in output

---

### Task 6.3: Run Full Test Suite
**Complexity**: LOW

```bash
make tests
./bin/phpunit --group architecture
```

**Success criteria**:
- PHPStan Level 9: PASS
- Unit tests: PASS
- Architecture tests: PASS
- Mutation testing: >= 79%

---

## Summary

| Phase | Tasks | New Files | Modified Files | Risk |
|-------|-------|-----------|----------------|------|
| **1. DTOs** | 4 | 4 | 0 | LOW |
| **2. Services** | 2 | 2 | 0 | LOW |
| **3. Interfaces** | 3 | 2 | 2 | LOW |
| **4. Refactor Long** | 3 | 0 | 3 | MEDIUM |
| **5. Inheritance** | 3 | 1 | 2 | MEDIUM |
| **6. Tests** | 3 | 2 | 0 | LOW |

**Total**: 18 tasks, ~11 new files, ~7 modified files

---

## Dependency Graph

```
Phase 1 (DTOs) ─────────────────────────────┐
   Task 1.1 (ResolveDataDTO) ──┐            │
   Task 1.2 (TransformerOutputDTO) ─┐       │
   Task 1.3 (MultimediaShotDTO) ────┤       │
   Task 1.4 (ShotsCollectionDTO) ───┘       │
                                            │
Phase 2 (Services) ◄────────────────────────┤
   Task 2.1 (MultimediaShotResolver) ◄──────┤
   Task 2.2 (MultimediaShotGenerator) ◄─────┘
                                            │
Phase 3 (Interfaces) ◄──────────────────────┤
   Task 3.1 (BodyElementTransformerInterface)
   Task 3.2 (Update Handler)                │
   Task 3.3 (Legacy Adapter)                │
                                            │
Phase 4 (Refactor) ◄────────────────────────┤
   Task 4.1 (DetailsAppsDataTransformer) ◄──┤
   Task 4.2 (BodyTagInsertedNews) ◄─────────┤
   Task 4.3 (RecommendedEditorials) ◄───────┘
                                            │
Phase 5 (Inheritance) ◄─────────────────────┤
   Task 5.1 (ListDataTransformer) ──────────┤
   Task 5.2 (UnorderedList) ◄───────────────┤
   Task 5.3 (NumberedList) ◄────────────────┘
                                            │
Phase 6 (Tests) ◄───────────────────────────┘
   Task 6.1 (Architecture Tests)
   Task 6.2 (Regression Tests)
   Task 6.3 (Full Suite)
```

**Parallelizable**:
- Tasks 1.1-1.4 can run in parallel
- Tasks 2.1-2.2 can run in parallel (after Phase 1)
- Tasks 4.1-4.3 can run in parallel (after Phase 2-3)
- Tasks 5.2-5.3 can run in parallel (after Task 5.1)

---

## Rollback Strategy

Each phase is independently revertable:
1. **Phase 1 (DTOs)**: New files only, delete to rollback
2. **Phase 2 (Services)**: New files only, delete to rollback
3. **Phase 3 (Interfaces)**: Adapter ensures backward compatibility
4. **Phase 4-5 (Refactor)**: Git revert individual commits
5. **Phase 6 (Tests)**: New files only, delete to rollback

**Recommended**: One commit per task for granular rollback.
