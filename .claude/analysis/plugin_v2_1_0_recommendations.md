# Plugin v2.1.0 - Recommendations for SNAAPI

**Date**: 2026-01-28
**Plugin Version**: 2.1.0
**Project**: SNAAPI (Symfony API Gateway)

---

## Executive Summary

Analysis of 7 new commands in workflow plugin v2.1.0. Recommendations for integration with SNAAPI development workflow.

---

## Command Analysis

### 1. `/workflows:parallel` - Parallel Agents with Git Worktrees

**Purpose**: Launch multiple agents in isolated worktrees for parallel development.

**SNAAPI Use Case**:
- Multi-layer DDD work (Domain + Application + Infrastructure)
- Backend only (no frontend), but useful for parallel refactoring

**Recommendation**: **MEDIUM PRIORITY**
- Use when refactoring multiple orchestrators simultaneously
- Example: Refactor SignatureFetcher + CommentsFetcher + ResponseAggregator in parallel

**Requirements**:
- tmux >= 3.0
- git >= 2.30

---

### 2. `/workflows:tdd` - TDD Enforcement

**Purpose**: Check TDD compliance and generate test templates.

**SNAAPI Use Case**:
- Enforce test-first development
- Verify PHPUnit tests exist before implementation

**Recommendation**: **HIGH PRIORITY**
- Integrate with pre-commit hooks
- Use `TDD_STRICTNESS=medium` for SNAAPI
- PHP test pattern: `*Test.php`

**Integration**:
```bash
# Add to .git/hooks/pre-commit
/workflows:tdd check
```

**Current Coverage**:
- 7 architecture tests in `tests/Architecture/`
- Unit tests for new services

---

### 3. `/workflows:trust` - Trust Level Evaluation

**Purpose**: Evaluate supervision requirements based on file/task type.

**SNAAPI Use Case**:
- Calibrate supervision per feature
- Already using manually in planning (Trust Level in 00_requirements.md)

**Recommendation**: **HIGH PRIORITY - Integrate into planning**

**SNAAPI Trust Mapping**:

| Pattern | Trust | Reason |
|---------|-------|--------|
| `tests/**/*` | HIGH | Safe to auto-approve |
| `src/Infrastructure/Config/*` | HIGH | Static configuration |
| `src/Application/DataTransformer/*` | MEDIUM | Business logic |
| `src/Orchestrator/*` | MEDIUM | External calls |
| `src/**/Security/*` | LOW | Security-sensitive |
| `config/packages/*` | LOW | Service configuration |

**Integration**:
```bash
# Before starting any task
/workflows:trust src/Orchestrator/Chain/EditorialOrchestrator.php
```

---

### 4. `/workflows:interview` - Guided Spec Creation

**Purpose**: Create feature specs through interactive interview.

**SNAAPI Use Case**:
- New feature specification
- API contract definition

**Recommendation**: **MEDIUM PRIORITY**
- Use for complex features
- Quick mode for simple features
- Generates YAML spec files

**When to Use**:
- New feature with unclear requirements
- API changes needing documentation
- Stakeholder-driven features

---

### 5. `/workflows:monitor` - Parallel Agent Monitoring

**Purpose**: Real-time status of parallel agents.

**SNAAPI Use Case**:
- Monitor multi-agent sessions
- Detect blocked agents

**Recommendation**: **LOW PRIORITY** (depends on `/workflows:parallel` usage)

**Useful Flags**:
- `--watch` - Real-time updates
- `--diagnose` - Issue detection
- `--json` - CI/CD integration

---

### 6. `/workflows:validate` - Spec Validation

**Purpose**: Validate YAML specs against JSON schemas.

**SNAAPI Use Case**:
- Validate feature specs before implementation
- Ensure spec completeness

**Recommendation**: **MEDIUM PRIORITY**
- Run after `/workflows:interview`
- Add to planning checklist

---

### 7. `/workflows:progress` - Long Session Tracking

**Purpose**: Track progress for long-running agent sessions.

**SNAAPI Use Case**:
- Complex refactoring tasks
- Multi-day features

**Recommendation**: **LOW PRIORITY** (nice-to-have)

---

## Priority Summary

| Priority | Command | Action |
|----------|---------|--------|
| **HIGH** | `/workflows:tdd` | Integrate with pre-commit |
| **HIGH** | `/workflows:trust` | Add to planning workflow |
| **MEDIUM** | `/workflows:parallel` | Use for multi-layer work |
| **MEDIUM** | `/workflows:interview` | Use for new features |
| **MEDIUM** | `/workflows:validate` | Add to planning checklist |
| **LOW** | `/workflows:monitor` | When using parallel |
| **LOW** | `/workflows:progress` | For long sessions |

---

## Recommended Integration

### 1. Planning Phase Enhancement

```markdown
## Planning Checklist (Updated)

- [ ] Read compound learnings
- [ ] Assign trust level: `/workflows:trust --task {type}`
- [ ] Create spec: `/workflows:interview feature` OR manual
- [ ] Validate spec: `/workflows:validate {spec.yaml}`
- [ ] Define tasks with TDD approach
```

### 2. Pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit

# TDD check
/workflows:tdd check

# Trust check for sensitive files
for file in $(git diff --cached --name-only); do
    if [[ "$file" == *"Security"* ]] || [[ "$file" == *"Auth"* ]]; then
        echo "LOW TRUST FILE: $file - requires review"
    fi
done
```

### 3. CI/CD Integration

```yaml
# GitLab CI
tdd-check:
  script:
    - /workflows:tdd check
  rules:
    - if: $CI_PIPELINE_SOURCE == "merge_request_event"
```

---

## SNAAPI-Specific Patterns

### Trust Configuration for SNAAPI

```yaml
# .ai/workflow/trust_model.yaml (recommended additions)

snaapi_patterns:
  high:
    - pattern: "tests/Architecture/*"
    - pattern: "src/Infrastructure/Config/*"
    - pattern: "tests/Unit/**/*Test.php"

  medium:
    - pattern: "src/Application/DataTransformer/**/*"
    - pattern: "src/Orchestrator/**/*"
    - pattern: "src/Application/DTO/*"

  low:
    - pattern: "src/EventSubscriber/*"
    - pattern: "config/packages/*"
    - pattern: "src/Exception/*"
```

---

## Next Steps

1. **Immediate**: Integrate `/workflows:trust` into planning template
2. **Short-term**: Set up `/workflows:tdd` pre-commit hook (when environment available)
3. **Medium-term**: Try `/workflows:parallel` for multi-orchestrator refactoring
4. **Long-term**: Full integration with CI/CD pipeline

---

## Compound Learning

This analysis should be captured in compound log:
- Plugin v2.1.0 provides good tooling for compound engineering
- Trust levels align with existing DDD layer structure
- TDD enforcement supports existing PHPUnit architecture tests
