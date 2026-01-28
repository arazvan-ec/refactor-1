# Feature: snaapi-pragmatic-refactor

**Created**: 2026-01-27
**Status**: PLANNING
**Trust Level**: 🟢 LOW CONTROL
**Workflow**: default

---

## Objective

Mejorar type safety y eliminar duplicación de código sin crear archivos innecesarios, aplicando los learnings del compound log.

---

## Context

### Work Completed (Phase 1-4)
- EditorialOrchestrator: 537 → 234 líneas (56% reducción)
- Dependencies: 18 → 9 (50% reducción)
- Tests: Comprehensive coverage, PHPStan Level 9 passing
- DTOs básicos creados (EditorialResponseDTO, MultimediaResponseDTO)

### Problem Statement
1. **Type safety incompleto**: Interfaces retornan `array<string, mixed>` - pierde type safety
2. **Código duplicado**: `SIZES_RELATIONS` aparece en 3 archivos (~230 líneas cada uno)
3. **Mixed responsibilities**: ExceptionSubscriber mezcla logging, error mapping, y response building

### What NOT To Do (From Compound Log)
- ❌ NO crear 25+ archivos DTO (anti-pattern: DTO Explosion)
- ❌ NO seguir spec 05 literalmente (over-engineered)
- ❌ NO optimizar async photo fetching sin medir latencia
- ❌ NO añadir abstracciones innecesarias

---

## Acceptance Criteria

### Mandatory
- [ ] `make test_stan` passes (Level 9)
- [ ] `make test_unit` passes
- [ ] `make test_cs` passes
- [ ] Máximo 1 archivo nuevo (`MultimediaImageSizes.php`)
- [ ] Zero cambios de comportamiento (solo refactor)

### Quality Gates
- [ ] No nuevas dependencias en composer.json
- [ ] Lines of code: neutral o decreased
- [ ] No `// TODO` o `// FIXME` introducidos

---

## Scope

### In Scope
1. PHPDoc array shapes para type safety (3 archivos)
2. Extracción de `SIZES_RELATIONS` a config class (1 archivo nuevo, 3 modificados)
3. Refactor interno de ExceptionSubscriber (1 archivo)

### Out of Scope
- Creación de DTOs adicionales
- Optimización de async photo fetching
- Cambios en contratos de API
- Modificaciones a tests existentes (solo nuevos tests para config class)

---

## Technical Approach

### Task 1: PHPDoc Array Shapes
Añadir PHPDoc con array shapes específicos en lugar de `array<string, mixed>`:

```php
// BEFORE
/** @return array<string, mixed> */
public function execute(Request $request): array

// AFTER
/**
 * @return array{
 *   id: string,
 *   url: string,
 *   titles: array{title: string, preTitle: string, urlTitle: string, mobileTitle: string},
 *   lead: string,
 *   publicationDate: string,
 *   ...
 * }
 */
public function execute(Request $request): array
```

**Files**:
- `src/Orchestrator/Chain/EditorialOrchestrator.php`
- `src/Application/Service/Editorial/ResponseAggregator.php`
- `src/Application/Service/Editorial/EmbeddedContentFetcher.php`

### Task 2: Extract SIZES_RELATIONS Config
Crear config class y actualizar referencias:

```php
// NEW: src/Infrastructure/Config/MultimediaImageSizes.php
final class MultimediaImageSizes
{
    public const ASPECT_RATIO_16_9 = '16:9';
    // ... other constants

    public const SIZES_RELATIONS = [
        self::ASPECT_RATIO_16_9 => [...],
        // ...
    ];
}
```

**Files to modify**:
- `src/Application/DataTransformer/Apps/Media/DataTransformers/DetailsMultimediaPhotoDataTransformer.php`
- `src/Application/DataTransformer/Apps/DetailsMultimediaDataTransformer.php`
- `src/Infrastructure/Service/PictureShots.php`

### Task 3: ExceptionSubscriber Cleanup
Extraer a métodos privados sin crear nuevas clases:

```php
// BEFORE: inline logic mixed
public function onKernelException(ExceptionEvent $event): void {
    // 50+ lines of mixed logic
}

// AFTER: delegated to private methods
public function onKernelException(ExceptionEvent $event): void {
    $response = $this->createErrorResponse($event->getThrowable());
    $this->logException($event->getThrowable());
    $event->setResponse($response);
}
```

**File**: `src/EventSubscriber/ExceptionSubscriber.php`

---

## Dependencies

### Prerequisites
- Git branch: `claude/refactor-api-workflow-uWOv9` (current)
- All tests passing before starting

### External Dependencies
- None

---

## Risks & Mitigations

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| PHPDoc shapes incorrect | Low | Low | PHPStan validates at Level 9 |
| Config extraction breaks behavior | Low | Medium | Run tests after each file change |
| Scope creep to add more DTOs | Medium | Medium | Stick to plan, review constraints |

---

## References

- Compound Log: `.claude/project/compound_log.md`
- Pragmatic Analysis: `.claude/analysis/00_pragmatic_refactor_analysis.md`
- Action Plan: `.claude/analysis/01_simplified_action_plan.md`
- Original Specs (for comparison, NOT to follow): `.claude/specs/05_typed_dtos_improvement_plan.md`

---

## Success Metrics

| Metric | Before | Target |
|--------|--------|--------|
| Files with `array<string, mixed>` returns | 3 | 0 (PHPDoc shapes) |
| SIZES_RELATIONS duplications | 3 files | 1 file |
| ExceptionSubscriber lines | 165 | ~120 |
| New files created | - | 1 |
| PHPStan Level | 9 ✅ | 9 ✅ |
