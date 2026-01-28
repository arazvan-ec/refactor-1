# Feature: Finalize and Next Phase

## Overview

**Feature ID**: finalize-and-next-phase
**Created**: 2026-01-28
**Trust Level**: MEDIUM CONTROL
**Plugin Version**: 2.1.0

---

## Objective

Cerrar el feature `snaapi-pragmatic-refactor` actualmente en progreso y definir la siguiente fase de trabajo basándose en compound learnings acumulados y las nuevas capacidades del plugin v2.1.0.

---

## Context

### Current State

| Feature | Status | Branch |
|---------|--------|--------|
| `snaapi-pragmatic-refactor` | Backend COMPLETED, QA PENDING | `claude/snaapi-pragmatic-refactor-vVG2f` |

### Work Completed in Previous Feature

1. **PHPDoc Array Shapes** - 3 métodos documentados
2. **MultimediaImageSizes** - Config class consolidando ~400 líneas duplicadas
3. **Architecture Enforcement** - TransformationLayerArchitectureTest creado
4. **SignatureFetcher + CommentsFetcher** - HTTP calls extraídos a capa correcta
5. **PreFetchedDataDTO** - Patrón para separar fetch de transform

### Plugin v2.1.0 New Capabilities

| Command | Purpose | Potential Use |
|---------|---------|---------------|
| `/workflows:parallel` | Git worktrees for parallel work | Acelerar trabajo multi-capa |
| `/workflows:tdd` | TDD compliance checking | Enforcement automático |
| `/workflows:trust` | Supervision calibration | Ajustar control por complejidad |
| `/workflows:validate` | YAML/JSON schema validation | Validar specs |
| `/workflows:interview` | Guided spec creation | Nuevos features |

### Compound Learnings Applied

**Patterns to use:**
- Service Extraction via TDD
- PHPDoc Array Shapes over DTOs
- Single Config Class for Constants
- Architecture Validation Tests
- Pre-Fetched Data DTO
- Service Extraction to Correct Layer

**Anti-patterns to avoid:**
- Massive Spec Documents (>200 líneas)
- DTO Hierarchy Explosion
- Optimizing Without Measuring
- Specs That Specify Implementation
- HTTP Calls in Transformation Layer

---

## Acceptance Criteria

### Phase 1: Close Current Feature (Priority: HIGH)

- [ ] All tests pass: `make tests`
- [ ] Architecture tests pass: `./bin/phpunit --group architecture`
- [ ] Code review completed using QA agent guidelines
- [ ] PR created (if not exists) and merged to main
- [ ] `50_state.md` updated to COMPLETED for all roles
- [ ] Compound capture completed with final learnings

### Phase 2: Define Next Feature (Priority: MEDIUM)

- [ ] Evaluated options from compound log recommendations
- [ ] Selected next feature based on VALUE/EFFORT ratio
- [ ] Trust level assigned to selected feature
- [ ] Requirements document created (< 200 lines per compound rule)

### Phase 3: Explore Plugin Capabilities (Priority: LOW)

- [ ] Documented when to use `/workflows:parallel`
- [ ] Tested `/workflows:tdd` on existing codebase
- [ ] Configured `/workflows:trust` for project

---

## Decision Matrix: Next Feature Options

Based on compound log analysis:

| Option | Value | Effort | ROI | Trust Level |
|--------|-------|--------|-----|-------------|
| A) More architecture tests | HIGH | 2-3h | Prevents regressions | LOW |
| B) DataTransformers audit | MEDIUM | 4-6h | Detect violations | MEDIUM |
| C) Performance baseline | MEDIUM | 2h | Data for decisions | LOW |
| D) Async photo batching | UNKNOWN | 4h+ | DEFER (measure first) | HIGH |

**Recommendation**: Option A or C first (compound log: "measure before optimize")

---

## Constraints

From compound learnings:

1. **Specs < 200 lines** - No massive spec documents
2. **No DTO explosion** - Use PHPDoc array shapes
3. **Measure before optimize** - Need data for Option D
4. **TDD always** - Tests before implementation

---

## Dependencies

- `snaapi-pragmatic-refactor` must complete QA before merge
- Main branch must be stable for next feature
- No external API changes required

---

## Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Tests fail in QA | LOW | MEDIUM | Run locally first |
| Merge conflicts | LOW | LOW | Rebase before merge |
| Scope creep in next feature | MEDIUM | HIGH | Apply spec size limits |

---

## References

- Previous feature: `.claude/features/snaapi-pragmatic-refactor/`
- Compound log: `.claude/project/compound_log.md`
- Architecture tests: `tests/Architecture/`
- Project rules: `.claude/rules/project_specific.md`

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Tests passing | 100% |
| Architecture violations | 0 |
| Time to close feature | < 2h |
| Next feature defined | Yes |
| Compound log updated | Yes |
