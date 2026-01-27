# Feature: DataTransformers Architecture Upgrade

**Feature ID**: plan-transformer-upgrade
**Created**: 2026-01-27
**Status**: ANALYSIS_COMPLETE
**Priority**: MEDIUM
**Trust Level**: MEDIUM CONTROL

---

## Executive Summary

### Is the upgrade necessary?

**YES**, but with targeted interventions, not a complete rewrite.

The current architecture is **fundamentally sound** (Chain of Responsibility + Template Method patterns are appropriate), but there are **6 specific issues** that violate SOLID/Clean Code principles and hinder scalability.

### Key Metrics

| Metric | Current | Target | Impact |
|--------|---------|--------|--------|
| Code Duplication | 3+ instances of same logic | 0 | HIGH |
| Type Safety | ~15 files use `array<string, mixed>` | Typed DTOs | HIGH |
| Max File Length | 211 lines | <100 lines | MEDIUM |
| Interfaces with `Interface` suffix | 40% | 100% | LOW |
| Architecture Test Coverage | 7 tests | 10+ tests | MEDIUM |

---

## Problem Analysis

### Issue 1: Code Duplication (Violates DRY)

**Severity**: HIGH
**Impact**: Maintenance nightmare, bug propagation risk

**Evidence**: Multimedia shot retrieval logic duplicated in:
- `BodyTagInsertedNewsDataTransformer.php` (lines 76-82)
- `RecommendedEditorialsDataTransformer.php` (lines 81-85)
- `DetailsMultimediaPhotoDataTransformer.php` (lines 66-80)
- `DetailsMultimediaDataTransformer.php` (lines 60-74)

```php
// DUPLICATED 3+ times:
if ($this->getMultimediaOpening($editorialId)) {
    $shots = $this->getMultimediaOpening($editorialId);
} else {
    $shots = $this->getMultimedia($editorialId);
}
```

**Solution**: Extract `MultimediaShotResolver` service.

---

### Issue 2: Generic Type Hints (Violates Type Safety)

**Severity**: HIGH
**Impact**: No IDE autocomplete, runtime errors, unclear contracts

**Evidence**: ~15+ files use `array<string, mixed>` including:
- `BodyDataTransformerInterface::execute(Body $body, array $resolveData)`
- `BodyTagInsertedNewsDataTransformer::$resolveData`
- `MediaDataTransformerHandler::execute(array $multimediaOpeningData)`

**Problem**: Callers don't know what keys/values to pass. Consumers don't know what structure to expect.

**Solution**: Create typed DTOs:
- `ResolveDataDTO` - input data for body transformers
- `TransformedBodyDTO` - output from body transformation
- `MultimediaResolveDTO` - multimedia-specific resolve data

---

### Issue 3: Long Methods (Violates SRP, Clean Code)

**Severity**: MEDIUM
**Impact**: Hard to test, maintain, and understand

**Evidence**:
| File | Lines | Longest Method |
|------|-------|----------------|
| `DetailsAppsDataTransformer.php` | 211 | `read()`: 64 lines |
| `RecommendedEditorialsDataTransformer.php` | 170 | `read()`: ~50 lines |
| `BodyTagInsertedNewsDataTransformer.php` | 143 | `read()`: 41 lines |

**Solution**: Extract helper methods, use composition over inheritance where appropriate.

---

### Issue 4: Inconsistent Interface Naming

**Severity**: LOW
**Impact**: Cognitive load, inconsistency

**Evidence**:
- `BodyElementDataTransformer` (no suffix)
- `AppsDataTransformer` (no suffix)
- `MediaDataTransformer` (no suffix)
- `BodyDataTransformerInterface` (has suffix)

**Solution**: Standardize all interfaces to use `Interface` suffix per PSR conventions.

---

### Issue 5: Duplicate Inheritance Paths

**Severity**: MEDIUM
**Impact**: Confusion about which implementation is used

**Evidence**:
```
ElementContentWithLinksDataTransformer
    └── UnorderedListDataTransformer  ← Path A

GenericListDataTransformer
    └── UnorderedListDataTransformer  ← Path B (DUPLICATE!)
```

**Solution**: Refactor to use composition with `LinksDataTransformer` trait only.

---

### Issue 6: Trait Interdependencies (Violates Clean Code)

**Severity**: LOW
**Impact**: Implicit contracts, hard to trace dependencies

**Evidence**: `MultimediaTrait` requires `setThumbor()` and `setExtension()` to be called:
```php
use MultimediaTrait;

public function __construct(string $extension, Thumbor $thumbor) {
    $this->setExtension($extension);
    $this->setThumbor($thumbor);  // Implicit dependency
}
```

**Solution**: Convert trait to injectable service `MultimediaShotGenerator`.

---

## What Works Well (Keep)

The following patterns are **correct and should be preserved**:

1. **Chain of Responsibility** via `BodyElementDataTransformerHandler`
2. **Template Method** via `ElementTypeDataTransformer` hierarchy
3. **Compiler Passes** for auto-registration
4. **Service Tags** (`app.data_transformer`, `app.media_data_transformer`)
5. **Architecture Tests** enforcing layer purity
6. **Fluent Interface** (`write()->read()` pattern)

---

## Proposed Solution Architecture

### New Components

```
src/
├── Application/
│   ├── DataTransformer/
│   │   ├── DTO/                              # NEW: Typed DTOs
│   │   │   ├── ResolveDataDTO.php
│   │   │   ├── TransformedBodyDTO.php
│   │   │   ├── MultimediaResolveDTO.php
│   │   │   └── TransformerOutputDTO.php
│   │   ├── Service/                          # NEW: Extracted services
│   │   │   ├── MultimediaShotResolver.php    # Extracted from 3+ transformers
│   │   │   └── MultimediaShotGenerator.php   # Converted from trait
│   │   └── Contract/                         # NEW: Standardized interfaces
│   │       ├── BodyElementTransformerInterface.php
│   │       ├── MediaTransformerInterface.php
│   │       └── TransformerOutputInterface.php
```

### Refactored Inheritance

**Before** (Confusing dual inheritance):
```
ElementTypeDataTransformer
├── ElementContentDataTransformer
│   └── ElementContentWithLinksDataTransformer
│       └── UnorderedListDataTransformer
└── GenericListDataTransformer
    └── UnorderedListDataTransformer  ← DUPLICATE
```

**After** (Clear single inheritance + composition):
```
ElementTypeDataTransformer
├── ElementContentDataTransformer
│   └── TextContentTransformer  # Renamed for clarity
│       ├── ParagraphDataTransformer
│       └── SubHeadDataTransformer
├── ListDataTransformer  # New base for all lists
│   ├── UnorderedListDataTransformer (uses LinksDataTransformerTrait)
│   └── NumberedListDataTransformer (uses LinksDataTransformerTrait)
└── MultimediaDataTransformer  # Base for media-related
    ├── BodyTagPictureDataTransformer
    └── BodyTagVideoDataTransformer
```

---

## Benefits After Refactoring

| Aspect | Before | After |
|--------|--------|-------|
| Add new transformer | Copy-paste shot logic | Inject `MultimediaShotResolver` |
| IDE autocomplete | No (array generic) | Yes (typed DTOs) |
| Understand `resolveData` | Read implementation | Read DTO definition |
| Test transformers | Mock 3+ trait methods | Mock 1 service |
| Max file length | 211 lines | <100 lines |
| Find bugs in shot logic | Check 4 files | Check 1 file |

### Scalability Improvements

**Adding a new body element transformer**:

```php
// Before: Copy-paste shot logic from another transformer
class NewElementDataTransformer extends ElementTypeDataTransformer
{
    use MultimediaTrait;  // Implicit contract

    public function __construct(
        string $extension,  // Must remember this
        Thumbor $thumbor,   // Must remember this
    ) {
        $this->setExtension($extension);  // Easy to forget
        $this->setThumbor($thumbor);      // Easy to forget
    }

    // Copy-paste shot resolution logic from another transformer...
}

// After: Clean dependency injection
class NewElementDataTransformer extends ElementTypeDataTransformer
{
    public function __construct(
        private readonly MultimediaShotResolver $shotResolver,  // Clear dependency
    ) {}

    public function read(): TransformerOutputDTO  // Typed output
    {
        $shots = $this->shotResolver->resolve($this->getResolveData());  // Single call
        // ...
    }
}
```

---

## Risk Assessment

### Trust Level: MEDIUM CONTROL

**Rationale**:
- Refactor of existing patterns (not greenfield)
- API response format unchanged
- Architecture tests will catch violations
- Similar patterns already proven in project

**Supervision Required**:
- [ ] Review DTOs before implementation
- [ ] Architecture tests must pass after each phase
- [ ] Regression tests comparing API output
- [ ] Mutation testing >= 79%

---

## Out of Scope

- Changing API response format
- Adding new transformers
- Modifying Orchestrator layer (separate feature)
- Changing external client interfaces

---

## Success Criteria

1. **No code duplication**: MultimediaShotResolver handles all shot logic
2. **Typed contracts**: All `array<string, mixed>` replaced with DTOs
3. **Single inheritance**: No duplicate inheritance paths
4. **File length**: No transformer > 100 lines
5. **Architecture tests**: 10+ tests pass
6. **Mutation testing**: >= 79% MSI
7. **API compatibility**: Response format unchanged

---

## References

- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [Clean Code by Robert C. Martin](https://www.amazon.com/Clean-Code-Handbook-Software-Craftsmanship/dp/0132350882)
- Current patterns: `BodyDataTransformerCompiler`, `EditorialOrchestratorCompiler`
- Related feature: `refactor-orchestrator-coupling`
