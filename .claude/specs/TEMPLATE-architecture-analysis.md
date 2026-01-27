# Architecture Analysis Spec Template

> **Usage**: Copy this template when running `/workflows:analyze --output=spec`
> **Naming**: `architecture-analysis-[YYYY-MM-DD].md`

---

# Architecture Analysis Spec

**Project**: SNAAPI
**Date**: [YYYY-MM-DD]
**Analyst**: [Role/Agent]
**Scope**: [all | specific layers]
**Depth**: [quick | standard | deep]

---

## Executive Summary

[2-3 sentences describing overall architecture health and key findings]

**Health Score**: [X/100]

| Category | Current | Target | Gap |
|----------|---------|--------|-----|
| Layer Purity | X% | 100% | X% |
| Avg Complexity | X | <10 | X |
| SOLID Compliance | X% | >90% | X% |
| DDD Compliance | X% | >95% | X% |

---

## Layer Analysis

### Exception Layer
**Status**: [✅ Clean | ⚠️ Warnings | ❌ Violations]
**Classes**: [count]

| Metric | Value | Status |
|--------|-------|--------|
| No dependencies | [Yes/No] | [status] |

**Findings**: [None | List]

---

### Application/DTO Layer
**Status**: [✅ Clean | ⚠️ Warnings | ❌ Violations]
**Classes**: [count]

| Metric | Value | Status |
|--------|-------|--------|
| Pure data structures | [Yes/No] | [status] |
| Typed properties | [%] | [status] |

**Findings**: [None | List]

---

### Application/DataTransformer Layer
**Status**: [✅ Clean | ⚠️ Warnings | ❌ Violations]
**Classes**: [count]

| Metric | Value | Status |
|--------|-------|--------|
| No HTTP clients | [Yes/No] | [status] |
| Implements interface | [%] | [status] |
| Has canTransform() | [%] | [status] |

**Findings**: [None | List]

---

### Application/Service Layer
**Status**: [✅ Clean | ⚠️ Warnings | ❌ Violations]
**Classes**: [count]

| Metric | Value | Status |
|--------|-------|--------|
| No HTTP clients | [Yes/No] | [status] |
| Single responsibility | [%] | [status] |

**Findings**: [None | List]

---

### Orchestrator/Chain Layer
**Status**: [✅ Clean | ⚠️ Warnings | ❌ Violations]
**Classes**: [count]

| Metric | Value | Status |
|--------|-------|--------|
| Implements interface | [%] | [status] |
| Avg complexity | [X] | [status] |
| Avg dependencies | [X] | [status] |

**Findings**: [None | List]

---

### Orchestrator/Service Layer
**Status**: [✅ Clean | ⚠️ Warnings | ❌ Violations]
**Classes**: [count]

| Metric | Value | Status |
|--------|-------|--------|
| HTTP clients allowed | ✅ | OK |
| Single responsibility | [%] | [status] |

**Findings**: [None | List]

---

### Infrastructure Layer
**Status**: [✅ Clean | ⚠️ Warnings | ❌ Violations]
**Classes**: [count]

| Metric | Value | Status |
|--------|-------|--------|
| No business logic | [Yes/No] | [status] |
| Proper isolation | [Yes/No] | [status] |

**Findings**: [None | List]

---

### Controller Layer
**Status**: [✅ Clean | ⚠️ Warnings | ❌ Violations]
**Classes**: [count]

| Metric | Value | Status |
|--------|-------|--------|
| Thin controllers | [Yes/No] | [status] |
| Only OrchestratorChain deps | [Yes/No] | [status] |

**Findings**: [None | List]

---

## Findings Detail

### 🔴 Critical Issues

#### C1: [Issue Title]
**Location**: `src/Path/To/File.php:line`
**Category**: [Layer Violation | Complexity | SOLID | DDD | Coupling]
**Description**: [What is wrong]
**Impact**: [Why it matters]
**Recommendation**: [How to fix]
**Effort**: [S | M | L | XL]
**Breaking Change**: [Yes | No]

---

### 🟠 High Priority Issues

#### H1: [Issue Title]
**Location**: `src/Path/To/File.php:line`
**Category**: [Category]
**Description**: [What is wrong]
**Impact**: [Why it matters]
**Recommendation**: [How to fix]
**Effort**: [S | M | L | XL]

---

### 🟡 Medium Priority Issues

#### M1: [Issue Title]
[Similar format]

---

### 🟢 Low Priority Issues

#### L1: [Issue Title]
[Similar format]

---

## Pattern Compliance

### Chain of Responsibility
**Status**: [✅ Implemented | ⚠️ Partial | ❌ Missing]

| Component | Status | Notes |
|-----------|--------|-------|
| OrchestratorChainHandler | [status] | [notes] |
| EditorialOrchestrator | [status] | [notes] |
| MultimediaOrchestratorHandler | [status] | [notes] |

### Strategy Pattern (DataTransformers)
**Status**: [✅ Implemented | ⚠️ Partial | ❌ Missing]

| Component | Status | Notes |
|-----------|--------|-------|
| BodyElementDataTransformerHandler | [status] | [notes] |
| Type-specific transformers | [count] | [notes] |

### Compiler Passes
**Status**: [✅ Implemented | ⚠️ Partial | ❌ Missing]

| Pass | Purpose | Status |
|------|---------|--------|
| EditorialOrchestratorCompiler | [purpose] | [status] |
| BodyDataTransformerCompiler | [purpose] | [status] |
| MediaDataTransformerCompiler | [purpose] | [status] |
| MultimediaOrchestratorCompiler | [purpose] | [status] |

---

## Complexity Hotspots

### Top 10 Most Complex Classes

| Rank | Class | LOC | Methods | Deps | Max CC | Action |
|------|-------|-----|---------|------|--------|--------|
| 1 | [class] | [X] | [X] | [X] | [X] | [action] |
| 2 | [class] | [X] | [X] | [X] | [X] | [action] |
| ... | ... | ... | ... | ... | ... | ... |

---

## Improvement Roadmap

### Phase 1: Critical Fixes (Immediate)
**Goal**: Eliminate layer violations, fix blocking issues
**Timeline**: 1-2 days
**Effort**: [S | M | L]

| # | Task | Issue Ref | Effort | Owner |
|---|------|-----------|--------|-------|
| 1.1 | [Task description] | C1 | S | - |
| 1.2 | [Task description] | C2 | M | - |

**Success Criteria**:
- [ ] All architecture tests pass
- [ ] No HTTP clients in Application layer
- [ ] [Other criteria]

---

### Phase 2: High Priority (Short-term)
**Goal**: Reduce complexity, improve maintainability
**Timeline**: 1-2 weeks
**Effort**: [M | L]

| # | Task | Issue Ref | Effort | Owner |
|---|------|-----------|--------|-------|
| 2.1 | [Task description] | H1 | M | - |
| 2.2 | [Task description] | H2 | L | - |

**Success Criteria**:
- [ ] No class exceeds 300 LOC
- [ ] No class has >10 dependencies
- [ ] [Other criteria]

---

### Phase 3: Medium Priority (Medium-term)
**Goal**: Improve DDD compliance, enhance patterns
**Timeline**: 2-4 weeks
**Effort**: [L | XL]

| # | Task | Issue Ref | Effort | Owner |
|---|------|-----------|--------|-------|
| 3.1 | [Task description] | M1 | L | - |
| 3.2 | [Task description] | M2 | L | - |

**Success Criteria**:
- [ ] 95% DDD compliance
- [ ] All patterns properly implemented
- [ ] [Other criteria]

---

### Phase 4: Optimization (Long-term)
**Goal**: Polish, optimization, nice-to-haves
**Timeline**: Ongoing
**Effort**: Variable

| # | Task | Issue Ref | Effort | Owner |
|---|------|-----------|--------|-------|
| 4.1 | [Task description] | L1 | S | - |

---

## Risk Assessment

### High Risk Changes

| Change | Risk | Mitigation |
|--------|------|------------|
| [Change] | [Risk description] | [Mitigation strategy] |

### Dependencies

| Task | Depends On | Blocks |
|------|------------|--------|
| [Task] | [Dependency] | [What it blocks] |

---

## Testing Strategy

### Existing Coverage
- Architecture tests: `./bin/phpunit --group architecture`
- Unit tests: `make test_unit`
- Static analysis: `make test_stan`

### Additional Tests Needed

| Finding | Test to Add | Type |
|---------|-------------|------|
| [Finding] | [Test description] | [Unit | Integration | Architecture] |

---

## Next Steps

1. **Review this spec** with stakeholders
2. **Create feature** via `/workflows:plan architecture-phase-1`
3. **Execute Phase 1** via `/workflows:work --mode=layers`
4. **Re-analyze** to measure improvement: `/workflows:analyze --depth=quick`

---

## Appendix

### A. Full Class Inventory
[Optional: List all analyzed classes with layer assignment]

### B. Dependency Graph
[Optional: Text representation of key dependencies]

### C. Historical Comparison
[Optional: Comparison with previous analysis if available]

---

**Generated by**: `/workflows:analyze`
**Skill**: `architecture-analyzer`
**Version**: 1.0
