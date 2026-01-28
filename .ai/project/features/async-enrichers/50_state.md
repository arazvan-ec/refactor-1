# State: Async Enrichers & Pipeline Steps

**Feature ID**: async-enrichers
**Last Updated**: 2026-01-27

---

## Current Phase

**Phase**: PLANNING COMPLETE
**Next**: VERIFICACIÓN DE CLIENTS (Phase 0)

---

## Checkpoints

| Checkpoint | Status | Notes |
|------------|--------|-------|
| Plan created | DONE | |
| Tasks breakdown | DONE | 15 tasks |
| Phase 0: Verificar clients | PENDING | BLOQUEANTE |
| Phase 1: Infraestructura | PENDING | |
| Phase 2: TagsEnricher async | PENDING | |
| Phase 3: PhotoBodyTagsEnricher async | PENDING | |
| Phase 4: FetchExternalDataStep | PENDING | |
| Phase 5: Wiring | PENDING | |
| Tests passing | PENDING | |

---

## Blockers

### BLOCKER-1: Verificar Soporte Async en Clients Externos

Antes de implementar, DEBE verificarse si los clients externos soportan `async: true`:

| Client | Package | Método | ¿Soporta? |
|--------|---------|--------|-----------|
| QueryTagClient | `ec/tag-client` | `findTagById` | **PENDIENTE** |
| QueryMultimediaClient | `ec/multimedia-client` | `findPhotoById` | **PENDIENTE** |
| QueryLegacyClient | `ec/legacy-client` | `findCommentsByEditorialId` | **PENDIENTE** |
| QueryJournalistClient | `ec/journalist-client` | `findJournalistByAliasId` | **PENDIENTE** |

**Acciones según resultado**:
- Si soportan: Implementar directamente
- Si no soportan:
  - Opción A: PR al package externo
  - Opción B: Crear wrapper con deferred promises

---

## Expected Performance Improvement

```
ANTES (secuencial):
Editorial con 5 tags + 3 fotos + 2 external:
  Tags: 5 × 200ms = 1000ms
  Fotos: 3 × 200ms = 600ms
  External: 2 × 200ms = 400ms
  TOTAL: 2000ms

DESPUÉS (paralelo):
  Tags: max(200ms) = 200ms
  Fotos: max(200ms) = 200ms
  External: max(200ms) = 200ms
  TOTAL: 600ms

MEJORA: 70%
```

---

## Notes

- El patrón ya existe en el proyecto (multimedia)
- Solo extendemos `PromiseResolver` con `resolveAll()`
- Fail-safe: si un promise falla, se loguea pero no rompe
