# Architecture: SNAAPI Refactor

**Feature**: snaapi-refactor
**Created**: 2026-01-25
**Status**: APPROVED

---

## Current Architecture Analysis

### EditorialOrchestrator (537 lines) - Main Pain Point

```
Current Structure:
┌─────────────────────────────────────────────────────────────────┐
│                   EditorialOrchestrator                          │
│  (537 lines - TOO MANY RESPONSIBILITIES)                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ Fetch       │  │ Process     │  │ Transform   │              │
│  │ Editorial   │  │ Inserted    │  │ Multimedia  │              │
│  │ + Section   │  │ News        │  │             │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
│                                                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ Process     │  │ Resolve     │  │ Build       │              │
│  │ Recommended │  │ Promises    │  │ Response    │              │
│  │ Editorials  │  │             │  │             │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
│                                                                  │
│  + 18 dependencies injected                                      │
│  + Multiple nested loops                                         │
│  + Complex promise resolution                                    │
│  + Mixed concerns (fetch, transform, aggregate)                  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Problems Identified

| Problem | Location | Impact |
|---------|----------|--------|
| God Class | `EditorialOrchestrator` | Hard to test, maintain |
| 18 dependencies | Constructor | Violation of SRP |
| Nested loops | `execute()` lines 126-207 | Cyclomatic complexity |
| Promise resolution inline | Lines 213-218 | Hard to test |
| Duplicate code | insertedNews/recommendedEditorials | DRY violation |
| Mixed concerns | Same class fetches, transforms, aggregates | SRP violation |
| Weak typing | `array<string, mixed>` everywhere | Runtime errors |

---

## Target Architecture

### Decomposed Structure

```
Target Structure:
┌─────────────────────────────────────────────────────────────────┐
│                   EditorialOrchestrator (SLIM)                   │
│  (~80-100 lines - ORCHESTRATION ONLY)                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  execute(Request $request): EditorialResponse                    │
│    1. Fetch editorial + section (via EditorialFetcher)           │
│    2. Fetch embedded content (via EmbeddedContentFetcher)        │
│    3. Resolve promises (via PromiseResolver)                     │
│    4. Aggregate response (via ResponseAggregator)                │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                     ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│ Editorial     │    │ Embedded      │    │ Promise       │
│ Fetcher       │    │ Content       │    │ Resolver      │
│               │    │ Fetcher       │    │               │
│ - editorial   │    │ - inserted    │    │ - settle()    │
│ - section     │    │ - recommended │    │ - callbacks   │
│ - tags        │    │ - multimedia  │    │ - error       │
│ - journalists │    │               │    │   handling    │
└───────────────┘    └───────────────┘    └───────────────┘
        │                     │                     │
        └─────────────────────┼─────────────────────┘
                              ▼
                    ┌───────────────┐
                    │ Response      │
                    │ Aggregator    │
                    │               │
                    │ - DTOs        │
                    │ - transformers│
                    │ - final build │
                    └───────────────┘
```

---

## New Classes to Create

### 1. Services (Application Layer)

| Class | Responsibility | Dependencies |
|-------|----------------|--------------|
| `EditorialFetcher` | Fetch editorial, section, tags, journalists | Clients |
| `EmbeddedContentFetcher` | Fetch inserted news, recommended editorials | Clients |
| `PromiseResolver` | Resolve Guzzle promises, handle errors | Logger |
| `ResponseAggregator` | Combine all data into final response | Transformers |

### 2. DTOs (Application Layer)

| Class | Purpose |
|-------|---------|
| `EditorialResponseDTO` | Typed response object |
| `FetchedEditorialDTO` | Editorial + section + tags data |
| `EmbeddedContentDTO` | Inserted news + recommended data |
| `MultimediaDTO` | Typed multimedia response |

### 3. Exceptions (Domain Layer)

| Class | When Thrown |
|-------|-------------|
| `EditorialFetchException` | External service failure |
| `PromiseResolutionException` | Promise resolution failure |
| `TransformationException` | Data transformation failure |

---

## File Structure Changes

### Before
```
src/
├── Orchestrator/
│   ├── Chain/
│   │   ├── EditorialOrchestrator.php (537 lines)
│   │   └── Multimedia/
│   └── OrchestratorChainHandler.php
```

### After
```
src/
├── Orchestrator/
│   ├── Chain/
│   │   ├── EditorialOrchestrator.php (~100 lines, slim)
│   │   └── Multimedia/
│   └── OrchestratorChainHandler.php
│
├── Application/
│   ├── Service/
│   │   ├── Editorial/
│   │   │   ├── EditorialFetcher.php (NEW)
│   │   │   ├── EmbeddedContentFetcher.php (NEW)
│   │   │   └── ResponseAggregator.php (NEW)
│   │   └── Promise/
│   │       └── PromiseResolver.php (NEW)
│   └── DTO/
│       ├── EditorialResponseDTO.php (NEW)
│       ├── FetchedEditorialDTO.php (NEW)
│       ├── EmbeddedContentDTO.php (NEW)
│       └── MultimediaDTO.php (NEW)
│
├── Exception/
│   ├── EditorialFetchException.php (NEW)
│   ├── PromiseResolutionException.php (NEW)
│   └── TransformationException.php (NEW)
```

---

## Refactoring Strategy

### Phase 1: Extract Services (Keep Tests Green)

**Strategy**: Extract-and-Delegate
1. Create new service class
2. Move method(s) to new class
3. Inject new class into EditorialOrchestrator
4. Delegate to new class
5. Run tests - must pass
6. Repeat

### Phase 2: Introduce DTOs (Type Safety)

**Strategy**: Gradual Replacement
1. Create DTO class
2. Update one method to return DTO
3. Update callers
4. Run tests
5. Repeat for next DTO

### Phase 3: Exception Handling

**Strategy**: Domain Exceptions
1. Create exception class
2. Replace generic catch with specific exception
3. Add context to exception
4. Update ExceptionSubscriber
5. Run tests

---

## Dependencies Graph

```
EditorialOrchestrator (NEW - slim)
    │
    ├──► EditorialFetcher
    │        ├── QueryEditorialClient
    │        ├── QuerySectionClient
    │        ├── QueryTagClient
    │        └── QueryJournalistClient
    │
    ├──► EmbeddedContentFetcher
    │        ├── QueryEditorialClient
    │        ├── QuerySectionClient
    │        └── QueryMultimediaClient
    │
    ├──► PromiseResolver
    │        └── LoggerInterface
    │
    └──► ResponseAggregator
             ├── AppsDataTransformer
             ├── BodyDataTransformer
             ├── JournalistsDataTransformer
             ├── MultimediaDataTransformer
             ├── StandfirstDataTransformer
             └── RecommendedEditorialsDataTransformer
```

---

## Backwards Compatibility

### API Contract: NO CHANGES

The public API response format must remain **identical**:

```json
{
  "id": "...",
  "title": "...",
  "body": [...],
  "signatures": [...],
  "multimedia": {...},
  "standfirst": {...},
  "recommendedEditorials": [...],
  "countComments": 0
}
```

### Verification

Golden master tests will capture current output and verify refactored code produces identical results.

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Breaking API response | Golden master tests comparing JSON output |
| Performance regression | Benchmark before/after each phase |
| Incomplete extraction | Small PRs, CI green at each step |
| Missing edge cases | Mutation testing to find weak tests |

---

## Success Metrics

| Metric | Current | Target | How to Measure |
|--------|---------|--------|----------------|
| EditorialOrchestrator lines | 537 | < 100 | `wc -l` |
| Constructor dependencies | 18 | < 6 | Count `__construct` params |
| Cyclomatic complexity | High | Low | PHPStan/Psalm |
| Test coverage | ~80% | > 85% | PHPUnit --coverage |
| Mutation score | 79% | > 80% | Infection |

---

## Decision Record

### ADR-001: Service Extraction Pattern

**Context**: EditorialOrchestrator has 537 lines and 18 dependencies.

**Decision**: Extract into 4 focused services (EditorialFetcher, EmbeddedContentFetcher, PromiseResolver, ResponseAggregator).

**Consequences**:
- Pros: Single responsibility, testable, maintainable
- Cons: More classes, initial refactoring effort

### ADR-002: DTO Introduction

**Context**: Excessive use of `array<string, mixed>` reduces type safety.

**Decision**: Introduce DTOs for response objects.

**Consequences**:
- Pros: Type safety, IDE support, self-documenting
- Cons: More boilerplate, migration effort

---

**Approved by**: Planner
**Date**: 2026-01-25
