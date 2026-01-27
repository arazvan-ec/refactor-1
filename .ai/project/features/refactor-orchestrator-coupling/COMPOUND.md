# Compound Log: Content Enricher Chain Pattern

**Feature ID**: refactor-orchestrator-coupling
**Completed**: 2026-01-27
**Compound Date**: 2026-01-27

---

## Summary

Implemented **Content Enricher Chain Pattern** to decouple `EditorialOrchestrator` from direct HTTP client dependencies.

---

## Metrics

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| EditorialOrchestrator lines | 278 | 148 | -47% |
| Constructor dependencies | 11 | 7 | -36% |
| Direct HTTP clients | 4 | 0 | -100% |
| Private methods in orchestrator | 5 | 0 | -100% |

---

## Patterns Learned

### 1. Content Enricher Pattern (New)

**When to use**: When multiple independent data sources need to contribute to a shared context.

**Structure**:
```
ChainHandler → Enricher1, Enricher2, ... EnricherN
    ↓
EditorialContext (mutable state)
```

**Key traits**:
- Auto-registration via service tags
- Priority-based ordering
- Fail-safe error handling (log and continue)
- `supports()` method for conditional execution

**Files created**:
- `src/Orchestrator/Enricher/ContentEnricherInterface.php`
- `src/Orchestrator/Enricher/ContentEnricherChainHandler.php`
- `src/Orchestrator/DTO/EditorialContext.php`
- `src/DependencyInjection/Compiler/ContentEnricherCompiler.php`

### 2. Compiler Pass Pattern (Existing, Reused)

**Pattern already existed** in codebase for:
- `BodyDataTransformerCompiler`
- `MediaDataTransformerCompiler`
- `MultimediaOrchestratorCompiler`

**Key insight**: Follow existing patterns for consistency. The team already knows how compiler passes work.

### 3. Mutable DTO Pattern

**When to use**: When multiple services need to contribute to a shared state during a single request.

**Trade-offs**:
- ✅ Simple to implement
- ✅ No need for immutable copies
- ❌ Not thread-safe (OK for PHP)
- ❌ State can be modified unexpectedly

**Best practice**: Use `withX()` naming for setters to indicate mutation.

---

## Anti-Patterns Avoided

### 1. HTTP Clients in Transformation Layer

**Rule**: Transformation classes (`*DataTransformer`, `*Aggregator`) must NEVER inject HTTP clients.

**Enforcement**: Architecture tests in `tests/Architecture/`

### 2. Fat Orchestrators

**Symptom**: Orchestrator with 10+ dependencies and 200+ lines.

**Solution**: Extract responsibilities to:
- Fetchers (for HTTP calls)
- Enrichers (for data decoration)
- Transformers (for format conversion)

---

## Decisions Made

| Decision | Rationale | Alternative Considered |
|----------|-----------|----------------------|
| Mutable context | Simpler than immutable copies | Immutable + return new |
| Fail-safe enrichers | Graceful degradation | Fail-fast |
| Priority numbers | Flexible ordering | Named dependencies |
| Keep SignatureFetcher/CommentsFetcher separate | Already abstracted | Convert to enrichers |

---

## Code Quality Observations

### What Worked Well

1. **Following existing patterns**: Compiler pass pattern was already established
2. **Interface-first design**: Created interface before implementation
3. **Incremental extraction**: Moved one responsibility at a time
4. **Test updates**: Updated tests alongside code changes

### What Could Be Improved

1. **Still too much logic in orchestrator**: 148 lines is better but not minimal
2. **Two patterns coexisting**: Enrichers + Fetchers could be unified
3. **No integration tests**: Only unit tests created

---

## Future Recommendations

### Immediate Next Step

Implement **Editorial Pipeline Pattern** to reduce orchestrator to ~15 lines.

### Long-term Considerations

1. **Unify patterns**: Consider converting all fetchers to pipeline steps
2. **Async steps**: Some steps could run in parallel (comments + signatures)
3. **Observability**: Add metrics/tracing per step
4. **Feature flags**: Steps could check feature flags before executing

---

## Files Modified

### Created
- `src/Orchestrator/Enricher/ContentEnricherInterface.php`
- `src/Orchestrator/Enricher/ContentEnricherChainHandler.php`
- `src/Orchestrator/Enricher/TagsEnricher.php`
- `src/Orchestrator/Enricher/MembershipLinksEnricher.php`
- `src/Orchestrator/Enricher/PhotoBodyTagsEnricher.php`
- `src/Orchestrator/DTO/EditorialContext.php`
- `src/DependencyInjection/Compiler/ContentEnricherCompiler.php`

### Modified
- `src/Orchestrator/Chain/EditorialOrchestrator.php`
- `src/Kernel.php`

### Tests Created
- `tests/Unit/Orchestrator/Enricher/ContentEnricherChainHandlerTest.php`
- `tests/Unit/Orchestrator/Enricher/TagsEnricherTest.php`
- `tests/Unit/Orchestrator/DTO/EditorialContextTest.php`

---

## Rules to Add to Project

### Rule: Orchestrator Simplicity

```markdown
## Orchestrator Guidelines

1. **Maximum dependencies**: 3 (handler, logger, optional config)
2. **Maximum lines**: 50 (including docblocks)
3. **Logic location**: In pipeline steps, not orchestrator
4. **Pattern**: Pipeline with auto-registered steps
```

### Rule: Step-Based Architecture

```markdown
## Pipeline Step Guidelines

1. **Single responsibility**: One step = one action
2. **Auto-registration**: Via service tags, not manual wiring
3. **Priority**: Higher number = executed first
4. **Results**: Continue, Skip, or Terminate
5. **Fail-safe**: Log errors, don't break chain (unless critical)
```

---

## Commit References

- `1da4aad`: plan(orchestrator): add Content Enricher Chain pattern
- `d46b3c2`: refactor(orchestrator): implement Content Enricher Chain pattern

---

## Tags

`#pattern:enricher-chain` `#pattern:compiler-pass` `#refactor` `#decoupling` `#solid`
