# State: Refactor EditorialOrchestrator Coupling

**Feature ID**: refactor-orchestrator-coupling
**Last Updated**: 2026-01-27

---

## Current Phase

**Phase**: PLANNING COMPLETE
**Next**: READY FOR IMPLEMENTATION

---

## Role Status

### Planner
**Status**: COMPLETED
**Last Action**: Created feature plan and task breakdown
**Notes**: Plan approved, ready for backend implementation

### Backend Engineer
**Status**: PENDING
**Assigned Tasks**: All tasks (this is a backend-only refactor)
**Blocked By**: None

### QA
**Status**: PENDING
**Notes**: Will review after implementation

---

## Checkpoints

| Checkpoint | Status | Notes |
|------------|--------|-------|
| Analysis complete | DONE | Identified 4 HTTP clients to extract |
| Plan created | DONE | Content Enricher Chain pattern selected |
| Tasks breakdown | DONE | 12 tasks across 4 phases |
| Implementation | PENDING | |
| Tests passing | PENDING | |
| Architecture tests | PENDING | |
| QA review | PENDING | |

---

## Implementation Order

```
1. [PENDING] ContentEnricherInterface
2. [PENDING] EditorialContext DTO
3. [PENDING] ContentEnricherCompiler
4. [PENDING] ContentEnricherChainHandler
5. [PENDING] TagsEnricher
6. [PENDING] MembershipLinksEnricher
7. [PENDING] PhotoBodyTagsEnricher
8. [PENDING] Refactor EditorialOrchestrator
9. [PENDING] Update ResponseAggregator
10. [PENDING] Configuration
11. [PENDING] Architecture tests
12. [PENDING] Regression tests
```

---

## Blockers

None currently.

---

## Questions for Review

1. ¿Debería `CommentsFetcher` y `SignatureFetcher` también convertirse en Enrichers?
   - **Decision pending**: Podría unificar todo el patrón
   - **Trade-off**: Más consistencia vs. más cambios

2. ¿Queremos soporte para enrichers async (promises)?
   - **Decision pending**: Podría mejorar performance
   - **Trade-off**: Más complejidad vs. mejor rendimiento

---

## Notes

- El patrón ya existe en el proyecto (DataTransformers, Orchestrators)
- No cambia el contrato de la API
- Refactor interno, bajo riesgo
