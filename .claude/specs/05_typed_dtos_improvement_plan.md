# Improvement Plan: Typed DTOs Between Layers

**Project**: SNAAPI
**Date**: 2026-01-26
**Status**: PLAN
**Priority**: HIGH

---

## 1. Problem Statement

### Current State

```
EditorialOrchestrator.execute()
    ↓ returns: array<string, mixed>  ← OPAQUE
ResponseAggregator.aggregate()
    ↓ builds: array<string, mixed>   ← OPAQUE
DataTransformer.write($resolveData)
    ↓ receives: array<string, mixed> ← NO CONTRACT
DataTransformer.read()
    ↓ returns: array<string, mixed>  ← NO STRUCTURE
```

### Problems

| Issue | Impact | Risk |
|-------|--------|------|
| No type safety | Runtime errors | HIGH |
| No IDE support | Slow development | MEDIUM |
| No contracts | Silent bugs on refactor | HIGH |
| Magic key access | `$data['key']` fails silently | HIGH |
| Undocumented structure | Hard to maintain | MEDIUM |

### Example of Current Risk

```php
// In BodyTagPictureDataTransformer
$shots = $this->resolveData()['multimedia'][$id];
// If 'multimedia' key doesn't exist → PHP Warning
// If $id doesn't exist → null, no error
// If structure changes → silent failure
```

---

## 2. Proposed Architecture

### Target State

```
EditorialOrchestrator.execute()
    ↓ returns: EditorialAggregateDTO        ← TYPED
ResponseAggregator.aggregate()
    ↓ builds: TransformContextDTO           ← TYPED
DataTransformer.write(TransformContextDTO)
    ↓ receives: TransformContextDTO         ← CONTRACT
DataTransformer.read()
    ↓ returns: BodyElementResponseDTO       ← TYPED
```

### DTO Hierarchy

```
src/Application/DTO/
├── Aggregate/                    # Orchestrator output
│   ├── EditorialAggregateDTO.php
│   └── EmbeddedContentAggregateDTO.php
│
├── Context/                      # Transform input context
│   ├── TransformContextDTO.php
│   ├── MultimediaContextDTO.php
│   └── MembershipContextDTO.php
│
├── Response/                     # Transform output
│   ├── Body/
│   │   ├── BodyElementResponseDTO.php (abstract)
│   │   ├── ParagraphResponseDTO.php
│   │   ├── SubHeadResponseDTO.php
│   │   ├── BodyTagPictureResponseDTO.php
│   │   └── ...
│   ├── MultimediaResponseDTO.php
│   ├── SignatureResponseDTO.php
│   └── EditorialResponseDTO.php
│
└── Collection/                   # Typed collections
    ├── BodyElementCollection.php
    ├── MultimediaCollection.php
    └── TagCollection.php
```

---

## 3. DTO Specifications

### 3.1 Aggregate DTOs (Orchestrator Output)

```php
/**
 * Output from EditorialOrchestrator
 * Contains all fetched data before transformation
 */
final readonly class EditorialAggregateDTO
{
    public function __construct(
        public Editorial $editorial,
        public Section $section,
        public EmbeddedContentAggregateDTO $embeddedContent,
        public MultimediaCollection $multimedia,
        public TagCollection $tags,
        public array $membershipLinks,  // TODO: MembershipLinkCollection
        public PhotoBodyTagCollection $photoBodyTags,
    ) {}
}

final readonly class EmbeddedContentAggregateDTO
{
    public function __construct(
        /** @var array<string, Editorial> */
        public array $insertedNews,
        /** @var array<string, Editorial> */
        public array $recommendedNews,
        /** @var array<string, Multimedia> */
        public array $multimedia,
    ) {}
}
```

### 3.2 Context DTOs (Transform Input)

```php
/**
 * Context passed to DataTransformers
 * Replaces array<string, mixed> $resolveData
 */
final readonly class TransformContextDTO
{
    public function __construct(
        public MultimediaContextDTO $multimedia,
        public InsertedNewsContextDTO $insertedNews,
        public RecommendedNewsContextDTO $recommendedNews,
        public MembershipContextDTO $membership,
        public PhotoBodyTagContextDTO $photoBodyTags,
    ) {}

    /**
     * Factory method from current buildResolveData output
     */
    public static function fromArray(array $resolveData): self
    {
        return new self(
            multimedia: MultimediaContextDTO::fromArray($resolveData['multimedia'] ?? []),
            insertedNews: InsertedNewsContextDTO::fromArray($resolveData['insertedNews'] ?? []),
            recommendedNews: RecommendedNewsContextDTO::fromArray($resolveData['recommendedEditorials'] ?? []),
            membership: MembershipContextDTO::fromArray($resolveData['membershipLinkCombine'] ?? []),
            photoBodyTags: PhotoBodyTagContextDTO::fromArray($resolveData['photoFromBodyTags'] ?? []),
        );
    }
}

final readonly class MultimediaContextDTO
{
    public function __construct(
        /** @var array<string, Multimedia> indexed by multimedia ID */
        private array $items,
    ) {}

    public function get(string $id): ?Multimedia
    {
        return $this->items[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->items[$id]);
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}

final readonly class PhotoBodyTagContextDTO
{
    public function __construct(
        /** @var array<string, PhotoBodyTag> indexed by photo ID */
        private array $items,
    ) {}

    public function getByPhotoId(string $photoId): ?PhotoBodyTag
    {
        return $this->items[$photoId] ?? null;
    }

    public function getAllForEditorial(string $editorialId): array
    {
        return array_filter(
            $this->items,
            fn(PhotoBodyTag $tag) => $tag->editorialId() === $editorialId
        );
    }
}
```

### 3.3 Response DTOs (Transform Output)

```php
/**
 * Base for all body element responses
 */
abstract readonly class BodyElementResponseDTO
{
    public function __construct(
        public string $type,
    ) {}

    abstract public function toArray(): array;
}

final readonly class ParagraphResponseDTO extends BodyElementResponseDTO
{
    public function __construct(
        public string $content,
        /** @var LinkDTO[]|null */
        public ?array $links = null,
    ) {
        parent::__construct('paragraph');
    }

    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'content' => $this->content,
        ];

        if ($this->links !== null) {
            $result['links'] = array_map(
                fn(LinkDTO $link) => $link->toArray(),
                $this->links
            );
        }

        return $result;
    }
}

final readonly class BodyTagPictureResponseDTO extends BodyElementResponseDTO
{
    public function __construct(
        public string $id,
        public ?string $caption,
        public ?string $alternate,
        public ?string $orientation,
        public ShotsDTO $shots,
    ) {
        parent::__construct('picture');
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'caption' => $this->caption,
            'alternate' => $this->alternate,
            'orientation' => $this->orientation,
            'shots' => $this->shots->toArray(),
            'url' => $this->shots->default,
        ];
    }
}

final readonly class ShotsDTO
{
    public function __construct(
        public string $default,
        public ?string $small = null,
        public ?string $medium = null,
        public ?string $large = null,
        public ?string $original = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'default' => $this->default,
            'small' => $this->small,
            'medium' => $this->medium,
            'large' => $this->large,
            'original' => $this->original,
        ], fn($v) => $v !== null);
    }
}
```

### 3.4 Typed Collections

```php
/**
 * Type-safe collection of body elements
 * @implements \IteratorAggregate<int, BodyElementResponseDTO>
 */
final class BodyElementCollection implements \IteratorAggregate, \Countable
{
    /** @var BodyElementResponseDTO[] */
    private array $elements = [];

    public function add(BodyElementResponseDTO $element): void
    {
        $this->elements[] = $element;
    }

    public function toArray(): array
    {
        return array_map(
            fn(BodyElementResponseDTO $el) => $el->toArray(),
            $this->elements
        );
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->elements);
    }

    public function count(): int
    {
        return count($this->elements);
    }
}
```

---

## 4. Refactored Interfaces

### 4.1 DataTransformer Interface (New)

```php
/**
 * BEFORE:
 * public function write(BodyElement $bodyElement, array $resolveData = []): self;
 * public function read(): array;
 *
 * AFTER:
 */
interface BodyElementDataTransformerInterface
{
    public function supports(BodyElement $element): bool;

    public function transform(
        BodyElement $element,
        TransformContextDTO $context
    ): BodyElementResponseDTO;
}
```

### 4.2 ResponseAggregator (Refactored)

```php
final class ResponseAggregator
{
    /**
     * BEFORE: aggregate(...arrays...) : array
     * AFTER: aggregate(EditorialAggregateDTO) : EditorialResponseDTO
     */
    public function aggregate(EditorialAggregateDTO $aggregate): EditorialResponseDTO
    {
        $context = $this->buildTransformContext($aggregate);

        $body = $this->transformBody(
            $aggregate->editorial->body(),
            $context
        );

        return new EditorialResponseDTO(
            id: $aggregate->editorial->id(),
            titles: $this->transformTitles($aggregate->editorial),
            body: $body,
            multimedia: $this->transformMultimedia($aggregate->multimedia),
            // ... other fields
        );
    }

    private function buildTransformContext(
        EditorialAggregateDTO $aggregate
    ): TransformContextDTO {
        return new TransformContextDTO(
            multimedia: MultimediaContextDTO::fromCollection($aggregate->multimedia),
            insertedNews: InsertedNewsContextDTO::from($aggregate->embeddedContent->insertedNews),
            recommendedNews: RecommendedNewsContextDTO::from($aggregate->embeddedContent->recommendedNews),
            membership: MembershipContextDTO::from($aggregate->membershipLinks),
            photoBodyTags: PhotoBodyTagContextDTO::from($aggregate->photoBodyTags),
        );
    }

    private function transformBody(
        Body $body,
        TransformContextDTO $context
    ): BodyElementCollection {
        $collection = new BodyElementCollection();

        foreach ($body->elements() as $element) {
            $transformer = $this->bodyTransformerHandler->getTransformer($element);
            $collection->add($transformer->transform($element, $context));
        }

        return $collection;
    }
}
```

---

## 5. Implementation Phases

### Phase 1: Foundation (Week 1)

**Goal**: Create DTO infrastructure without breaking existing code

| Task | Files | Risk |
|------|-------|------|
| Create Context DTOs | 5 new files | LOW |
| Create Response DTOs (Body) | 11 new files | LOW |
| Create Collections | 3 new files | LOW |
| Add `toArray()` methods | All DTOs | LOW |

**Deliverable**: DTO classes exist, not yet used

### Phase 2: Context Migration (Week 2)

**Goal**: Replace `$resolveData` array with `TransformContextDTO`

| Task | Files to Modify | Risk |
|------|-----------------|------|
| Create `TransformContextDTO::fromArray()` | 1 file | LOW |
| Update `ResponseAggregator.buildResolveData()` | 1 file | MEDIUM |
| Update `BodyDataTransformer` to accept DTO | 1 file | MEDIUM |
| Update all `ElementTypeDataTransformer` | 11 files | MEDIUM |

**Strategy**: Gradual migration with backward compatibility

```php
// Transition: Accept both array and DTO
public function write(
    BodyElement $element,
    array|TransformContextDTO $context = []
): self {
    if (is_array($context)) {
        $context = TransformContextDTO::fromArray($context);
    }
    $this->context = $context;
    // ...
}
```

### Phase 3: Response Migration (Week 3)

**Goal**: DataTransformers return DTOs instead of arrays

| Task | Files to Modify | Risk |
|------|-----------------|------|
| Update transformer interface | 1 file | LOW |
| Update `ParagraphDataTransformer` | 1 file | MEDIUM |
| Update all Body transformers | 10 files | MEDIUM |
| Update `BodyElementCollection` | 1 file | LOW |

**Strategy**: Transform returns DTO, `toArray()` at final step

```php
// New interface
public function transform(
    BodyElement $element,
    TransformContextDTO $context
): BodyElementResponseDTO;

// Implementation
public function transform(
    BodyElement $element,
    TransformContextDTO $context
): ParagraphResponseDTO {
    return new ParagraphResponseDTO(
        content: $element->content(),
        links: $this->extractLinks($element),
    );
}
```

### Phase 4: Orchestrator Migration (Week 4)

**Goal**: Orchestrator returns `EditorialAggregateDTO`

| Task | Files to Modify | Risk |
|------|-----------------|------|
| Create `EditorialAggregateDTO` | 1 new file | LOW |
| Update `EditorialOrchestrator.execute()` | 1 file | HIGH |
| Update `ResponseAggregator.aggregate()` | 1 file | HIGH |
| Update tests | Multiple | MEDIUM |

### Phase 5: Final Response (Week 5)

**Goal**: Full typed response from Controller to JSON

| Task | Files to Modify | Risk |
|------|-----------------|------|
| Create `EditorialResponseDTO` | 1 file | LOW |
| Update Controller to use DTO | 1 file | LOW |
| Add JSON serialization | 1 file | LOW |
| Full integration tests | Multiple | MEDIUM |

---

## 6. Migration Strategy: Strangler Fig Pattern

```
Week 1: Build new (DTOs exist alongside arrays)
         ┌─────────────────────────────────────┐
         │  array<string, mixed> (current)    │
         │  DTO classes (new, unused)         │
         └─────────────────────────────────────┘

Week 2-3: Gradual replacement (both work)
         ┌─────────────────────────────────────┐
         │  array → TransformContextDTO       │
         │  array ← BodyElementResponseDTO    │
         └─────────────────────────────────────┘

Week 4-5: Complete migration (arrays removed)
         ┌─────────────────────────────────────┐
         │  EditorialAggregateDTO              │
         │  TransformContextDTO                │
         │  EditorialResponseDTO               │
         └─────────────────────────────────────┘
```

---

## 7. Testing Strategy

### Unit Tests for DTOs

```php
class TransformContextDTOTest extends TestCase
{
    public function testFromArrayCreatesValidDTO(): void
    {
        $array = [
            'multimedia' => ['id1' => $this->createMultimedia()],
            'insertedNews' => [],
            'recommendedEditorials' => [],
            'membershipLinkCombine' => [],
            'photoFromBodyTags' => [],
        ];

        $dto = TransformContextDTO::fromArray($array);

        $this->assertTrue($dto->multimedia->has('id1'));
        $this->assertFalse($dto->multimedia->has('nonexistent'));
    }

    public function testFromArrayHandlesMissingKeys(): void
    {
        $dto = TransformContextDTO::fromArray([]);

        $this->assertFalse($dto->multimedia->has('any'));
        // No exception thrown
    }
}
```

### Integration Tests

```php
class EditorialOrchestratorIntegrationTest extends TestCase
{
    public function testExecuteReturnsTypedDTO(): void
    {
        $result = $this->orchestrator->execute($request);

        $this->assertInstanceOf(EditorialAggregateDTO::class, $result);
        $this->assertInstanceOf(Section::class, $result->section);
        $this->assertInstanceOf(Editorial::class, $result->editorial);
    }
}
```

---

## 8. Rollback Plan

Each phase can be rolled back independently:

| Phase | Rollback Action |
|-------|-----------------|
| 1 | Delete unused DTO files |
| 2 | Revert to `array $resolveData` |
| 3 | Revert transformer interface |
| 4 | Revert orchestrator return type |
| 5 | Revert controller |

**Feature flags** (optional):
```php
if ($this->config->get('use_typed_dtos')) {
    return $this->aggregateWithDTOs($data);
} else {
    return $this->aggregateWithArrays($data);
}
```

---

## 9. Success Metrics

| Metric | Before | After |
|--------|--------|-------|
| PHPStan errors (level 9) | ~0 | 0 |
| `array<string, mixed>` occurrences | ~50 | <5 |
| IDE autocomplete coverage | ~30% | >90% |
| Runtime type errors | Possible | Impossible |
| Refactoring safety | Low | High |

---

## 10. Questions for Decision

1. **Naming convention**: `*DTO` vs `*Data` vs `*Response`?
2. **Immutability**: All DTOs `readonly`? (recommended: yes)
3. **Serialization**: `toArray()` method vs `JsonSerializable`?
4. **Validation**: Validate in constructor or separate validator?
5. **Collections**: Custom collection classes or `array<Type>`?

---

## 11. Next Steps

1. **Review this plan** with team
2. **Approve Phase 1** scope
3. **Create feature branch** `feature/typed-dtos`
4. **Start with TransformContextDTO** (highest impact)
5. **Iterate** based on learnings
