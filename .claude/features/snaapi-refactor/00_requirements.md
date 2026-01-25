# Feature: SNAAPI Refactor - Best Practices Implementation

**Feature ID**: snaapi-refactor
**Created**: 2026-01-25
**Status**: PLANNING

---

## Executive Summary

Refactor SNAAPI codebase to implement better practices, improve code quality, testability, and maintainability while preserving all existing functionality.

---

## Current State Analysis

### Strengths (Keep)
- Well-defined bounded contexts via external clients
- Chain of Responsibility pattern for orchestrators
- Strategy pattern for DataTransformers
- Compiler Passes for extensibility
- Async processing with Guzzle Promises
- Good test infrastructure (PHPUnit, PHPStan, Mutation testing)

### Pain Points (Fix)

| Area | Issue | Impact | Priority |
|------|-------|--------|----------|
| EditorialOrchestrator | 537 lines, multiple responsibilities | Hard to test, hard to maintain | P1 |
| Type Safety | Excessive `array<string, mixed>` | Runtime errors, poor IDE support | P2 |
| Error Handling | Generic try-catch, dispersed logging | Poor observability | P3 |
| Namespace Structure | `Ec/Snaapi` confusing | Developer confusion | P4 |
| Promise Resolution | Complex nested callbacks | Hard to follow, test | P1 |
| Code Duplication | Similar logic in orchestrators | DRY violation | P2 |

---

## Refactoring Goals

### 1. SOLID Principles Enforcement

#### Single Responsibility
- [ ] Split EditorialOrchestrator into focused collaborators
- [ ] Extract PromiseResolutionService
- [ ] Create EmbeddedEditorialsFetcher
- [ ] Separate transformation from orchestration

#### Open/Closed
- [ ] Ensure new body types can be added without modifying existing code
- [ ] Review Compiler Pass patterns for completeness

#### Liskov Substitution
- [ ] Verify all interface implementations are truly substitutable
- [ ] Remove any interface violations

#### Interface Segregation
- [ ] Review interface sizes, split if too large
- [ ] Create focused interfaces for specific use cases

#### Dependency Inversion
- [ ] Ensure all dependencies are on abstractions
- [ ] Remove any concrete class dependencies in constructors

### 2. Clean Code Improvements

#### Meaningful Names
- [ ] Review all class, method, and variable names
- [ ] Ensure names reveal intent
- [ ] Apply ubiquitous language consistently

#### Small Functions
- [ ] Refactor methods > 20 lines
- [ ] Extract helper methods with clear purposes
- [ ] Remove deep nesting (max 2-3 levels)

#### No Comments for Bad Code
- [ ] Remove comments that explain "what"
- [ ] Keep comments that explain "why" (business rules)
- [ ] Refactor complex code instead of commenting

### 3. Type Safety

#### DTOs for Responses
- [ ] Create EditorialResponseDTO
- [ ] Create MultimediaResponseDTO
- [ ] Create BodyElementResponseDTO
- [ ] Remove `array<string, mixed>` where possible

#### Value Objects
- [ ] Identify candidates for Value Objects
- [ ] Implement immutable VOs where appropriate

### 4. Error Handling

#### Domain Exceptions
- [ ] Create EditorialNotFoundException
- [ ] Create MultimediaNotFoundException
- [ ] Create TransformationException
- [ ] Create OrchestratorException

#### Centralized Handling
- [ ] Review ExceptionSubscriber
- [ ] Ensure consistent error response format
- [ ] Add structured logging context

### 5. Testing Improvements

#### Testability
- [ ] Inject PromiseFactory for easier mocking
- [ ] Create test doubles for external clients
- [ ] Reduce coupling in orchestrators

#### Coverage
- [ ] Maintain > 80% unit test coverage
- [ ] Add integration tests for orchestrator chains
- [ ] Add edge case tests for transformers

---

## Refactoring Phases

### Phase 1: EditorialOrchestrator Decomposition (P1)
**Goal**: Split 537-line orchestrator into manageable pieces

1. Extract `PromiseResolutionService`
   - Handle promise creation and resolution
   - Centralize callback management

2. Extract `EmbeddedEditorialsFetcher`
   - Handle inserted news fetching
   - Handle recommended editorials fetching

3. Extract `MultimediaAggregator`
   - Coordinate multimedia orchestration
   - Handle media transformation

4. Simplify `EditorialOrchestrator::execute()`
   - Max 30 lines
   - Clear flow: fetch → aggregate → transform

### Phase 2: Type Safety (P2)

1. Create Response DTOs
   - `Application/DTO/EditorialResponse.php`
   - `Application/DTO/MultimediaResponse.php`
   - `Application/DTO/BodyElementResponse.php`

2. Update DataTransformers
   - Return DTOs instead of arrays
   - Update interfaces

3. Update Orchestrators
   - Use DTOs internally
   - Type all method signatures

### Phase 3: Error Handling (P3)

1. Create Domain Exceptions
   - `Exception/EditorialNotFoundException.php`
   - `Exception/TransformationException.php`
   - `Exception/OrchestratorException.php`

2. Update ExceptionSubscriber
   - Map exceptions to HTTP status codes
   - Add structured logging

3. Add Error Context
   - Include editorial ID in errors
   - Include transformation context

### Phase 4: Namespace Cleanup (P4)

1. Move Legacy Client
   - From: `Ec/Snaapi/Client/QueryLegacyClient`
   - To: `Infrastructure/Client/Legacy/QueryLegacyClient`

2. Update Configuration
   - Update service definitions
   - Update namespaces in config files

---

## Success Criteria

| Metric | Current | Target |
|--------|---------|--------|
| EditorialOrchestrator lines | 537 | < 100 |
| Max method lines | ~80 | < 20 |
| `array<string, mixed>` usage | High | Minimal |
| PHPStan Level | 9 | 9 (no new errors) |
| Test Coverage | ~80% | > 80% |
| Mutation Score | 79% | > 79% |

---

## Constraints

1. **No Behavior Change**: All existing API responses must remain identical
2. **No External Changes**: Client libraries remain unchanged
3. **Incremental**: Each PR should be independently mergeable
4. **Test First**: Write/update tests before refactoring
5. **CI Green**: All tests must pass at each step

---

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Breaking API contract | High | Golden master tests, contract tests |
| Performance regression | Medium | Benchmark before/after |
| Incomplete refactor | Medium | Small PRs, clear checkpoints |
| Test coverage drop | Medium | Enforce coverage in CI |

---

## Next Steps

1. [ ] Run current test suite to establish baseline
2. [ ] Create golden master tests for API responses
3. [ ] Begin Phase 1: EditorialOrchestrator decomposition
4. [ ] Review and iterate

---

**Status**: Ready for `/workflows:plan snaapi-refactor`
