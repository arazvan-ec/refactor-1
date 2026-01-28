# Task Breakdown: Finalize and Next Phase

## Overview

**Feature**: finalize-and-next-phase
**Total Tasks**: 8
**Estimated Time**: 3-4 hours

---

## Phase 1: Close Current Feature

### Task QA-001: Run Full Test Suite

**Role**: QA
**Reference**: `make tests` command
**Methodology**: Validation

**Steps**:
1. Run `make tests` (full suite)
2. Run `./bin/phpunit --group architecture` (layer validation)
3. Document any failures

**Acceptance Criteria**:
- [ ] `make test_unit` passes
- [ ] `make test_stan` passes (PHPStan Level 9)
- [ ] `make test_cs` passes (code style)
- [ ] `./bin/phpunit --group architecture` passes

**Verification**:
```bash
make tests && ./bin/phpunit --group architecture
```

**Escape Hatch**: If tests fail, document in `50_state.md` with exact error messages.

---

### Task QA-002: Code Review

**Role**: QA
**Reference**: Files changed in `snaapi-pragmatic-refactor`

**Review Checklist**:
- [ ] PHPDoc array shapes correctly typed
- [ ] MultimediaImageSizes follows single config pattern
- [ ] SignatureFetcher/CommentsFetcher in correct layer (Orchestrator)
- [ ] ResponseAggregator has no HTTP client dependencies
- [ ] PreFetchedDataDTO properly used
- [ ] No SIZES_RELATIONS duplication remains

**Files to Review**:
```
src/Infrastructure/Config/MultimediaImageSizes.php
src/Application/DTO/PreFetchedDataDTO.php
src/Orchestrator/Service/SignatureFetcher.php
src/Orchestrator/Service/CommentsFetcher.php
src/Application/Service/Editorial/ResponseAggregator.php
tests/Architecture/TransformationLayerArchitectureTest.php
```

**Acceptance Criteria**:
- [ ] All files follow DDD layer rules
- [ ] No architectural violations detected
- [ ] Code is clean and readable

---

### Task QA-003: Merge to Main

**Role**: QA
**Reference**: Git workflow in `global_rules.md`

**Steps**:
1. Ensure all tests pass
2. Create PR if not exists
3. Review PR description
4. Merge to main
5. Delete feature branch (optional)

**Acceptance Criteria**:
- [ ] PR approved
- [ ] Merged to main without conflicts
- [ ] Main branch stable after merge

**Verification**:
```bash
git checkout main && git pull && make tests
```

---

### Task COMPOUND-001: Final Compound Capture

**Role**: Planner
**Reference**: `.claude/project/compound_log.md`

**Steps**:
1. Document final learnings from this feature
2. Answer compound questions:
   - Did architecture test detect false positives?
   - Were new patterns effective?
   - Any resistance to PreFetchedDataDTO?
3. Update metrics table

**Acceptance Criteria**:
- [ ] Compound log updated with final entry
- [ ] Time investment documented
- [ ] Patterns confirmed or adjusted

---

## Phase 2: Define Next Feature

### Task PLAN-001: Evaluate Options

**Role**: Planner
**Reference**: Decision Matrix in `00_requirements.md`

**Options to Evaluate**:

| Option | Action Required |
|--------|-----------------|
| A) More architecture tests | List additional layers to validate |
| B) DataTransformers audit | Count transformers, estimate violations |
| C) Performance baseline | Define metrics to capture |

**Steps**:
1. For Option A: List layers not yet validated
2. For Option C: Identify latency measurement points
3. Score each option: VALUE * (1/EFFORT)
4. Select winner

**Acceptance Criteria**:
- [ ] Each option evaluated with data
- [ ] Winner selected with justification
- [ ] Decision documented

---

### Task PLAN-002: Create Next Feature Spec

**Role**: Planner
**Reference**: Compound rule - specs < 200 lines

**Steps**:
1. Create directory: `.claude/features/{selected-feature}/`
2. Create `00_requirements.md` (< 200 lines)
3. Create `30_tasks.md` (< 50 lines per task)
4. Create `50_state.md` (initial state)
5. Assign Trust Level

**Acceptance Criteria**:
- [ ] Spec created and under 200 lines
- [ ] Tasks have clear acceptance criteria
- [ ] Trust level documented

---

## Phase 3: Plugin Exploration (Optional)

### Task EXPLORE-001: Document Parallel Workflows

**Role**: Planner
**Reference**: `/workflows:parallel` command

**Steps**:
1. Read `.claude/commands/workflows/parallel.md`
2. Identify use cases for SNAAPI project
3. Document in project rules when to use

**Deliverable**: Addition to `project_specific.md` or DECISIONS.md

---

### Task EXPLORE-002: Test TDD Enforcement

**Role**: QA
**Reference**: `/workflows:tdd` command

**Steps**:
1. Read `.claude/commands/workflows/tdd.md`
2. Run on existing codebase
3. Document results and recommendations

**Deliverable**: TDD compliance report

---

## Summary

| Phase | Tasks | Est. Time |
|-------|-------|-----------|
| Phase 1: Close | QA-001, QA-002, QA-003, COMPOUND-001 | 1.5-2h |
| Phase 2: Plan | PLAN-001, PLAN-002 | 1-1.5h |
| Phase 3: Explore | EXPLORE-001, EXPLORE-002 | 0.5-1h |
| **Total** | 8 tasks | 3-4.5h |

---

## Execution Order

```
QA-001 (tests)
    ↓
QA-002 (review)
    ↓
QA-003 (merge) → COMPOUND-001 (capture)
    ↓
PLAN-001 (evaluate)
    ↓
PLAN-002 (spec)
    ↓
EXPLORE-001 + EXPLORE-002 (parallel, optional)
```
