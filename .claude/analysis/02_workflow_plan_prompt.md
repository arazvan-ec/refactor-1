# Optimal Prompt for /workflows:plan

Este documento contiene el prompt optimizado para iniciar la planificación del refactor pragmático.

---

## Command to Execute

```
/workflows:plan snaapi-pragmatic-refactor
```

---

## Input Prompt (Copy This)

```markdown
## Feature: snaapi-pragmatic-refactor

### Contexto
El refactor Fase 1-4 de SNAAPI ya se completó exitosamente:
- EditorialOrchestrator: 537 → 234 líneas (56% reducción)
- Dependencies: 18 → 9 (50% reducción)
- Tests: Comprehensive coverage, PHPStan Level 9 passing

### Problema a Resolver
Quedan tres mejoras de alto valor identificadas en el análisis pragmático:
1. Type safety incompleto (`array<string, mixed>` en interfaces)
2. Constantes duplicadas en DataTransformers (SIZES_RELATIONS)
3. ExceptionSubscriber con responsabilidades mezcladas

### Lo que NO hay que hacer (Compound Learning)
- NO crear 25+ archivos DTO - usar PHPDoc array shapes
- NO optimizar async photo fetching sin medir latencia primero
- NO seguir specs 05-06 tal como están escritos

### Objetivos
1. Añadir PHPDoc array shapes a métodos clave (type safety sin nuevos archivos)
2. Extraer `MultimediaImageSizes` config class (eliminar duplicación)
3. Refactorizar ExceptionSubscriber a métodos privados (no nuevas clases)

### Criterios de Aceptación
- [ ] `make test_stan` pasa (Level 9)
- [ ] `make test_unit` pasa
- [ ] Máximo 1 archivo nuevo (MultimediaImageSizes.php)
- [ ] Zero cambios de comportamiento (solo refactor)
- [ ] Tiempo total < 6 horas

### Referencias (Compound Patterns)
- Pattern: Service Extraction via TDD → `.claude/project/compound_log.md`
- Pattern: PHPDoc Array Shapes → `.claude/analysis/01_simplified_action_plan.md`
- Anti-pattern: DTO Explosion → Evitar spec 05

### Archivos a Modificar
```
src/Orchestrator/Chain/EditorialOrchestrator.php (PHPDoc)
src/Application/Service/Editorial/ResponseAggregator.php (PHPDoc)
src/Application/Service/Editorial/EmbeddedContentFetcher.php (PHPDoc)
src/Application/DataTransformer/Apps/Media/DataTransformers/DetailsMultimediaPhotoDataTransformer.php (extract config)
src/Application/DataTransformer/Apps/Media/DataTransformers/DetailsMultimediaDataTransformer.php (extract config)
src/EventSubscriber/ExceptionSubscriber.php (refactor methods)
```

### Archivo a Crear
```
src/Infrastructure/Config/MultimediaImageSizes.php
```

### Metodología
- TDD para extracción de config
- Verificación con `make test_stan` después de cada cambio
- Commits pequeños e incrementales

### Trust Level
🟢 LOW CONTROL - Patrones establecidos, código existente, refactor simple
```

---

## Why This Prompt Works

### 1. Context-Rich but Concise
- Resume trabajo previo en 3 líneas
- Lista exacta de archivos (no exploración necesaria)
- Límites claros de scope

### 2. Anti-Goals Explicit
- "Lo que NO hay que hacer" previene over-engineering
- Referencias a compound learning

### 3. Measurable Acceptance Criteria
- Tests específicos a pasar
- Límite de archivos nuevos
- Tiempo máximo

### 4. References Compound Patterns
- Conecta con learnings previos
- Agente puede consultar patrones documentados

### 5. Trust Level Specified
- 🟢 LOW CONTROL = menos supervisión necesaria
- Permite al agente trabajar autónomamente

---

## Alternative: Ultra-Minimal Prompt

Si prefieres un prompt más corto:

```markdown
## Feature: snaapi-pragmatic-refactor

Mejorar type safety y eliminar duplicación sin crear nuevos archivos innecesarios.

### Tasks
1. PHPDoc array shapes en EditorialOrchestrator, ResponseAggregator, EmbeddedContentFetcher
2. Extraer SIZES_RELATIONS a MultimediaImageSizes.php
3. Refactorizar ExceptionSubscriber (métodos privados, no nuevas clases)

### Constraints
- Máximo 1 archivo nuevo
- `make test_stan` y `make test_unit` deben pasar
- NO seguir spec 05 (DTO explosion)

### Reference
`.claude/project/compound_log.md` para patrones a reutilizar
```

---

## Expected Output from /workflows:plan

El comando debería generar:

```
.claude/features/snaapi-pragmatic-refactor/
├── 00_requirements.md      # Feature definition
├── 30_tasks.md             # 3 tasks (one per objective)
└── 50_state.md             # State tracking
```

Con tasks como:

```markdown
### Task BE-001: Add PHPDoc Array Shapes
**Role**: Backend Engineer
**Priority**: P1
**Methodology**: Incremental annotation
**Max Iterations**: 5

**Files**:
- src/Orchestrator/Chain/EditorialOrchestrator.php
- src/Application/Service/Editorial/ResponseAggregator.php
- src/Application/Service/Editorial/EmbeddedContentFetcher.php

**Acceptance Criteria**:
- [ ] Array shapes documented for execute(), aggregate(), fetch() methods
- [ ] `make test_stan` passes
- [ ] IDE shows autocomplete for return types

**Verification**:
make test_stan
```

---

## After Planning

Once /workflows:plan completes, execute with:

```
/workflows:work --mode=layers --layer=application snaapi-pragmatic-refactor
```

Or manually:
1. Read `30_tasks.md`
2. Mark Task BE-001 as IN_PROGRESS in `50_state.md`
3. Implement, test, commit
4. Repeat for remaining tasks
