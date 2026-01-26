# Tasks: SNAAPI Refactor

**Feature**: snaapi-refactor
**Created**: 2026-01-25
**Methodology**: TDD (Test-Driven Development)
**Max Iterations per Task**: 10 (Ralph Wiggum Pattern)

---

## Phase 1: EditorialOrchestrator Decomposition

### Task BE-001: Create PromiseResolver Service

**Role**: Backend Engineer
**Priority**: P1
**Reference**: `src/Orchestrator/Chain/EditorialOrchestrator.php:484-509`
**Methodology**: TDD (Red-Green-Refactor)

**Requirements**:
- Extract promise resolution logic to dedicated service
- Handle `Utils::settle()` and callbacks
- Centralize error handling for failed promises
- Return typed results instead of mixed arrays

**Files to Create**:
- `src/Application/Service/Promise/PromiseResolver.php`
- `src/Application/Service/Promise/PromiseResolverInterface.php`
- `tests/Unit/Application/Service/Promise/PromiseResolverTest.php`

**TDD Approach**:
1. 🔴 RED: Write test for `resolveMultimedia()` method
2. 🟢 GREEN: Implement minimal PromiseResolver
3. 🔵 REFACTOR: Clean up code
4. 🔴 RED: Write test for error handling (failed promises)
5. 🟢 GREEN: Implement error handling
6. 🔵 REFACTOR: Extract callback creation

**Tests to Write FIRST**:
```php
- [ ] test_resolve_multimedia_returns_array_of_multimedia()
- [ ] test_resolve_multimedia_handles_failed_promises()
- [ ] test_resolve_multimedia_logs_errors()
- [ ] test_create_callback_returns_closure()
```

**Acceptance Criteria**:
- [ ] PromiseResolver exists in `src/Application/Service/Promise/`
- [ ] Interface defined for dependency inversion
- [ ] All promise resolution moved from EditorialOrchestrator
- [ ] Tests pass with > 80% coverage
- [ ] PHPStan level 9 passes

**Verification**:
```bash
./bin/phpunit tests/Unit/Application/Service/Promise/
make test_stan
```

**🚨 Escape Hatch**: If blocked after 10 iterations, document in DECISIONS.md with alternatives.

---

### Task BE-002: Create EditorialFetcher Service

**Role**: Backend Engineer
**Priority**: P1
**Reference**: `src/Orchestrator/Chain/EditorialOrchestrator.php:98-116`
**Methodology**: TDD

**Requirements**:
- Fetch editorial by ID
- Fetch associated section
- Handle legacy fallback
- Check visibility (throw EditorialNotPublishedYetException)

**Files to Create**:
- `src/Application/Service/Editorial/EditorialFetcher.php`
- `src/Application/Service/Editorial/EditorialFetcherInterface.php`
- `src/Application/DTO/FetchedEditorialDTO.php`
- `tests/Unit/Application/Service/Editorial/EditorialFetcherTest.php`

**TDD Approach**:
1. 🔴 RED: Write test for `fetch()` returning DTO
2. 🟢 GREEN: Implement EditorialFetcher
3. 🔵 REFACTOR: Extract DTO
4. 🔴 RED: Write test for legacy fallback
5. 🟢 GREEN: Implement legacy handling
6. 🔵 REFACTOR: Clean up

**Tests to Write FIRST**:
```php
- [ ] test_fetch_returns_dto_with_editorial_and_section()
- [ ] test_fetch_throws_exception_when_not_visible()
- [ ] test_fetch_returns_legacy_when_no_source()
- [ ] test_fetch_throws_exception_when_not_found()
```

**Acceptance Criteria**:
- [ ] EditorialFetcher exists in `src/Application/Service/Editorial/`
- [ ] FetchedEditorialDTO created with typed properties
- [ ] EditorialOrchestrator delegates to EditorialFetcher
- [ ] Tests pass with > 80% coverage

**Verification**:
```bash
./bin/phpunit tests/Unit/Application/Service/Editorial/
./bin/phpunit tests/Unit/Orchestrator/Chain/EditorialOrchestratorTest.php
```

---

### Task BE-003: Create EmbeddedContentFetcher Service

**Role**: Backend Engineer
**Priority**: P1
**Reference**: `src/Orchestrator/Chain/EditorialOrchestrator.php:126-207`
**Methodology**: TDD

**Requirements**:
- Fetch inserted news (BodyTagInsertedNews)
- Fetch recommended editorials
- Fetch signatures for each
- Collect multimedia promises
- Return typed DTO

**Files to Create**:
- `src/Application/Service/Editorial/EmbeddedContentFetcher.php`
- `src/Application/Service/Editorial/EmbeddedContentFetcherInterface.php`
- `src/Application/DTO/EmbeddedContentDTO.php`
- `src/Application/DTO/InsertedNewsDTO.php`
- `src/Application/DTO/RecommendedEditorialDTO.php`
- `tests/Unit/Application/Service/Editorial/EmbeddedContentFetcherTest.php`

**TDD Approach**:
1. 🔴 RED: Write test for `fetchInsertedNews()`
2. 🟢 GREEN: Implement inserted news fetching
3. 🔵 REFACTOR: Extract to DTO
4. 🔴 RED: Write test for `fetchRecommendedEditorials()`
5. 🟢 GREEN: Implement recommended fetching
6. 🔵 REFACTOR: Remove duplication between inserted/recommended

**Tests to Write FIRST**:
```php
- [ ] test_fetch_inserted_news_returns_array_of_dtos()
- [ ] test_fetch_inserted_news_skips_invisible_editorials()
- [ ] test_fetch_recommended_editorials_returns_array_of_dtos()
- [ ] test_fetch_recommended_handles_errors_gracefully()
- [ ] test_fetch_collects_multimedia_promises()
```

**Acceptance Criteria**:
- [ ] EmbeddedContentFetcher exists
- [ ] Duplicate code eliminated (DRY)
- [ ] DTOs typed properly (no mixed)
- [ ] Error handling with logging
- [ ] Tests pass with > 80% coverage

**Verification**:
```bash
./bin/phpunit tests/Unit/Application/Service/Editorial/EmbeddedContentFetcherTest.php
```

---

### Task BE-004: Create ResponseAggregator Service

**Role**: Backend Engineer
**Priority**: P1
**Reference**: `src/Orchestrator/Chain/EditorialOrchestrator.php:232-275`
**Methodology**: TDD

**Requirements**:
- Aggregate all fetched data into final response
- Coordinate transformers (body, multimedia, standfirst, etc.)
- Build signatures array
- Return EditorialResponseDTO

**Files to Create**:
- `src/Application/Service/Editorial/ResponseAggregator.php`
- `src/Application/Service/Editorial/ResponseAggregatorInterface.php`
- `src/Application/DTO/EditorialResponseDTO.php`
- `tests/Unit/Application/Service/Editorial/ResponseAggregatorTest.php`

**TDD Approach**:
1. 🔴 RED: Write test for `aggregate()` method
2. 🟢 GREEN: Implement aggregation
3. 🔵 REFACTOR: Clean up transformer calls
4. 🔴 RED: Write test for signatures building
5. 🟢 GREEN: Implement signatures
6. 🔵 REFACTOR: Extract common patterns

**Tests to Write FIRST**:
```php
- [ ] test_aggregate_returns_editorial_response_dto()
- [ ] test_aggregate_includes_body_content()
- [ ] test_aggregate_includes_multimedia()
- [ ] test_aggregate_includes_signatures()
- [ ] test_aggregate_includes_standfirst()
- [ ] test_aggregate_includes_recommended_editorials()
```

**Acceptance Criteria**:
- [ ] ResponseAggregator exists
- [ ] EditorialResponseDTO fully typed
- [ ] All transformers coordinated properly
- [ ] Tests pass with > 80% coverage

---

### Task BE-005: Refactor EditorialOrchestrator to Use New Services

**Role**: Backend Engineer
**Priority**: P1
**Reference**: `src/Orchestrator/Chain/EditorialOrchestrator.php`
**Methodology**: TDD

**Requirements**:
- Replace inline code with service calls
- Reduce constructor dependencies from 18 to ~6
- Keep `execute()` method under 30 lines
- Maintain exact same API response

**Changes to Make**:
```php
// BEFORE: 18 dependencies, 537 lines
public function __construct(
    private readonly QueryLegacyClient $queryLegacyClient,
    private readonly QueryEditorialClient $queryEditorialClient,
    // ... 16 more
)

// AFTER: ~6 dependencies, ~100 lines
public function __construct(
    private readonly EditorialFetcherInterface $editorialFetcher,
    private readonly EmbeddedContentFetcherInterface $embeddedContentFetcher,
    private readonly PromiseResolverInterface $promiseResolver,
    private readonly ResponseAggregatorInterface $responseAggregator,
    private readonly LoggerInterface $logger,
)
```

**TDD Approach**:
1. 🔴 RED: Update existing tests for new constructor
2. 🟢 GREEN: Inject new services
3. 🔵 REFACTOR: Remove old dependencies
4. Run golden master tests to verify output unchanged

**Acceptance Criteria**:
- [ ] EditorialOrchestrator < 100 lines
- [ ] Constructor < 6 dependencies
- [ ] All existing tests pass
- [ ] Golden master tests pass (API response identical)
- [ ] PHPStan level 9 passes

**Verification**:
```bash
./bin/phpunit tests/Unit/Orchestrator/Chain/EditorialOrchestratorTest.php
./bin/phpunit tests/Integration/
make test_stan
```

---

## Phase 2: Type Safety (DTOs)

### Task BE-006: Create EditorialResponseDTO

**Role**: Backend Engineer
**Priority**: P2
**Methodology**: TDD

**Requirements**:
- Fully typed properties (no mixed)
- Immutable (readonly)
- toArray() method for backwards compatibility

**File to Create**:
- `src/Application/DTO/EditorialResponseDTO.php`

**Structure**:
```php
final readonly class EditorialResponseDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $subtitle,
        public array $body,
        public array $signatures,
        public ?MultimediaDTO $multimedia,
        public StandfirstDTO $standfirst,
        public array $recommendedEditorials,
        public int $countComments,
        // ... other typed fields
    ) {}

    public function toArray(): array
    {
        // For backwards compatibility
    }
}
```

**Acceptance Criteria**:
- [ ] DTO has all required fields
- [ ] All fields properly typed
- [ ] toArray() produces identical output to current array
- [ ] Tests verify type safety

---

### Task BE-007: Create MultimediaDTO

**Role**: Backend Engineer
**Priority**: P2

**Files to Create**:
- `src/Application/DTO/MultimediaDTO.php`
- `src/Application/DTO/PhotoDTO.php`
- `src/Application/DTO/VideoDTO.php`

**Acceptance Criteria**:
- [ ] DTOs cover all multimedia types
- [ ] Type safety enforced
- [ ] Backwards compatible

---

### Task BE-008: Update DataTransformers to Return DTOs

**Role**: Backend Engineer
**Priority**: P2

**Files to Modify**:
- `src/Application/DataTransformer/Apps/AppsDataTransformer.php`
- `src/Application/DataTransformer/Apps/MultimediaDataTransformer.php`
- `src/Application/DataTransformer/Apps/StandfirstDataTransformer.php`

**Acceptance Criteria**:
- [ ] Transformers return DTOs
- [ ] All callers updated
- [ ] Tests pass

---

## Phase 3: Exception Handling

### Task BE-009: Create Domain Exceptions

**Role**: Backend Engineer
**Priority**: P3

**Files to Create**:
- `src/Exception/EditorialFetchException.php`
- `src/Exception/PromiseResolutionException.php`
- `src/Exception/TransformationException.php`

**Each Exception Must Have**:
- Contextual information (editorial ID, etc.)
- Previous exception support
- Static factory methods

**Example**:
```php
final class EditorialFetchException extends \RuntimeException
{
    public static function notFound(string $id, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Editorial with ID "%s" not found', $id),
            404,
            $previous
        );
    }
}
```

---

### Task BE-010: Update ExceptionSubscriber

**Role**: Backend Engineer
**Priority**: P3

**File to Modify**:
- `src/EventSubscriber/ExceptionSubscriber.php`

**Requirements**:
- Map new exceptions to HTTP status codes
- Add structured logging context
- Ensure consistent error response format

---

## Phase 4: Cleanup

### Task BE-011: Move QueryLegacyClient

**Role**: Backend Engineer
**Priority**: P4

**Move From**: `src/Ec/Snaapi/Infrastructure/Client/Http/QueryLegacyClient.php`
**Move To**: `src/Infrastructure/Client/Legacy/QueryLegacyClient.php`

**Requirements**:
- Update namespace
- Update all imports
- Update service configuration

---

### Task BE-012: Remove Dead Code

**Role**: Backend Engineer
**Priority**: P4

**Requirements**:
- Remove unused private methods
- Remove commented code
- Remove unused imports

---

## QA Tasks

### Task QA-001: Create Golden Master Tests

**Role**: QA Engineer
**Priority**: P1

**Requirements**:
- Capture current API response for known editorials
- Store as JSON fixtures
- Compare refactored output against fixtures

**Files to Create**:
- `tests/GoldenMaster/EditorialResponseTest.php`
- `tests/Fixtures/editorial_response_*.json`

---

### Task QA-002: Run Full Test Suite After Each Phase

**Role**: QA Engineer
**Priority**: P1

**After Each Phase**:
```bash
make tests  # Full suite (CS, YAML, container, unit, stan, mutation)
```

**Criteria**:
- All tests green
- Coverage > 80%
- Mutation score > 79%
- PHPStan level 9 passes

---

### Task QA-003: Performance Benchmarking

**Role**: QA Engineer
**Priority**: P2

**Requirements**:
- Benchmark `execute()` before refactor
- Benchmark after each phase
- Ensure no regression > 10%

---

## Commit Strategy

### Commit After Each Task

```bash
# Example commit messages
git commit -m "feat(orchestrator): extract PromiseResolver service

- Create PromiseResolver with resolve/callback methods
- Add PromiseResolverInterface for DI
- Add unit tests with 85% coverage
- Delegate from EditorialOrchestrator

Refs: BE-001"

git commit -m "feat(orchestrator): extract EditorialFetcher service

- Create EditorialFetcher with fetch() method
- Create FetchedEditorialDTO
- Handle legacy fallback
- Add unit tests

Refs: BE-002"
```

### Push After Each Phase

```bash
# After completing all Phase 1 tasks
git push -u origin claude/clone-private-gitlab-repo-1IVfj
```

---

## Task Dependencies

```
Phase 1 (Must be sequential):
BE-001 (PromiseResolver) ─┐
BE-002 (EditorialFetcher) ├──► BE-005 (Refactor Orchestrator)
BE-003 (EmbeddedContent)  │
BE-004 (ResponseAggregator)┘

Phase 2 (Can parallelize):
BE-006 (EditorialResponseDTO) ─┐
BE-007 (MultimediaDTO)         ├──► BE-008 (Update Transformers)
                               ┘

Phase 3:
BE-009 (Exceptions) ──► BE-010 (ExceptionSubscriber)

Phase 4:
BE-011 (Move Legacy) ──► BE-012 (Remove Dead Code)

QA (Parallel with development):
QA-001 (Golden Master) ──► Continuous verification
```

---

## Summary

| Phase | Tasks | Priority | Estimated Complexity |
|-------|-------|----------|---------------------|
| Phase 1 | BE-001 to BE-005 | P1 | High |
| Phase 2 | BE-006 to BE-008 | P2 | Medium |
| Phase 3 | BE-009 to BE-010 | P3 | Low |
| Phase 4 | BE-011 to BE-012 | P4 | Low |
| QA | QA-001 to QA-003 | P1-P2 | Medium |

**Total Tasks**: 15
**Approach**: TDD for all tasks
**Escape Hatch**: 10 iterations max, then document in DECISIONS.md
