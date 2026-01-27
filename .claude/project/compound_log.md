# Compound Log - SNAAPI

Registro acumulativo de learnings para hacer el trabajo futuro más fácil.

---

## 2026-01-27: snaapi-refactor-phase1 + Pragmatic Analysis

### Summary

Análisis del refactor completado (Fase 1-4) y evaluación crítica de specs propuestos para fases futuras.

**Resultado clave**: El refactor Fase 1-4 entregó valor real. Los specs propuestos para fases futuras (25+ archivos DTO) representan over-engineering.

### Time Investment (Fase 1-4 Completed)
- Planning: 3 hours (30%)
- Implementation: 5 hours (50%)
- Review: 1 hour (10%)
- Compound: 1 hour (10%)
- **Total**: 10 hours

### The 70% Boundary Analysis

#### Where did the 70% end?
**Milestone**: EditorialOrchestrator decomposition complete, basic DTOs created
**Time spent**: 6 hours (60% of total)

#### What made the 30% hard?
1. **Over-specification trap**
   - Specs propuestos tienen 2000+ líneas para mejoras incrementales
   - Tiempo perdido leyendo/entendiendo specs vs implementando
   - **Lección**: Specs más cortos = mejor para agentes AI

2. **DTO explosion anti-pattern identified**
   - Spec 05 propone 25+ nuevos archivos DTO
   - Cada archivo = overhead de mantenimiento
   - PHPDoc array shapes logran 90% del beneficio con 0 archivos
   - **Lección**: Preferir anotaciones sobre nuevas clases

3. **Premature optimization trap**
   - Spec 06 optimiza N+1 photo fetching sin medir latencia real
   - No hay datos que justifiquen la complejidad
   - **Lección**: Medir antes de optimizar

4. **Analysis paralysis from too many specs**
   - 9 specs detallados crean parálisis de análisis
   - Agente necesita contexto claro y accionable
   - **Lección**: Un plan simple > múltiples specs detallados

### Patterns to Reuse (HIGH VALUE)

#### Pattern 1: Service Extraction via TDD
**Where**: `src/Application/Service/Editorial/`
**Why it worked**:
- Tests escritos primero garantizaron comportamiento
- Refactor seguro con red de tests
- EditorialOrchestrator: 537 → 234 líneas

**Recomendación**: Siempre extraer servicios con TDD
```
1. Write test for extracted service
2. Create minimal implementation
3. Update orchestrator to delegate
4. Verify existing tests pass
5. Delete dead code
```

#### Pattern 2: PHPDoc Array Shapes over DTOs
**Where**: Propuesto en análisis pragmático
**Why it works**:
- PHPStan Level 9 valida estructura
- IDE autocomplete funciona
- Zero runtime overhead
- Zero archivos nuevos

**Recomendación**: Usar para tipos de retorno complejos
```php
/**
 * @return array{
 *   id: string,
 *   title: string,
 *   items: list<array{name: string, value: int}>
 * }
 */
public function getData(): array
```

#### Pattern 3: Single Config Class for Constants
**Where**: Propuesto - `MultimediaImageSizes`
**Why it works**:
- Elimina duplicación (200+ líneas en 2 transformers)
- Single source of truth
- Fácil de actualizar

**Recomendación**: Extraer constantes duplicadas a config class

### Anti-Patterns Documented (AVOID)

#### Anti-Pattern 1: Massive Spec Documents
**Example**: Spec 05 - 643 líneas para typed DTOs
**Problem**:
- Demasiado contexto para procesar
- Agente pierde foco en detalles
- Specs se vuelven obsoletos

**Rule**: Specs < 200 líneas. Si necesitas más, divide en features separados.

#### Anti-Pattern 2: DTO Hierarchy Explosion
**Example**: Propuesta de 25+ archivos DTO
**Problem**:
- Cada archivo = maintenance burden
- Mapping code entre DTOs
- Cognitive overhead

**Rule**: Solo crear DTOs cuando:
1. Se reutilizan en 3+ lugares
2. Tienen comportamiento (factory methods, validación)
3. PHPDoc no es suficiente

#### Anti-Pattern 3: Optimizing Without Measuring
**Example**: Spec 06 - Async photo fetching
**Problem**:
- No hay datos de latencia real
- Solución compleja para problema hipotético

**Rule**: Antes de optimizar:
1. Medir latencia actual (p50, p95, p99)
2. Identificar umbral aceptable
3. Solo optimizar si datos lo justifican

#### Anti-Pattern 4: Specs That Specify Implementation
**Example**: Specs detallando estructura exacta de clases
**Problem**:
- Agente sigue instrucciones ciegamente
- Pierde capacidad de simplificar

**Rule**: Specs deben especificar:
- QUÉ problema resolver
- Criterios de aceptación
- NO CÓMO implementar

### Rules Updated

#### Addition to project_specific.md
```markdown
## Agent-Friendly Refactoring Guidelines

### Prefer Simple Solutions
1. PHPDoc array shapes > new DTO classes
2. Extract to private methods > new services
3. Config constants > dependency injection for static data

### Measure Before Optimize
- No performance refactoring without latency data
- Document current metrics before proposing changes

### Spec Size Limits
- Feature specs: < 200 lines
- Task breakdown: < 50 lines per task
- If larger, split into multiple features
```

### Impact on Future Work

#### For next refactor (v2-improvements):
- Skip DTO explosion - use PHPDoc instead
- Defer async optimization - measure first
- Focus on: Image sizes extraction (clear ROI)

#### Time savings estimate:
- Without compound learning: 40+ hours (following all specs)
- With compound learning: 8-10 hours (pragmatic approach)
- **Savings: 75%**

### Proposals for Next Feature

Based on this analysis, three concrete proposals:

| Proposal | Value | Effort | Priority |
|----------|-------|--------|----------|
| PHPDoc Array Shapes | HIGH | 2h | P1 |
| Image Sizes Config | HIGH | 1h | P1 |
| Exception Cleanup | MEDIUM | 1h | P2 |
| Batch Photo Fetching | UNKNOWN | 2h | DEFER |

### Questions for Future

1. ¿Hay métricas de latencia de API disponibles?
2. ¿Cuántas fotos promedio por editorial?
3. ¿El CDN mitiga el problema N+1?

### Files Reference

- Analysis: `.claude/analysis/00_pragmatic_refactor_analysis.md`
- Action Plan: `.claude/analysis/01_simplified_action_plan.md`
- Original Specs: `.claude/specs/05_typed_dtos_improvement_plan.md` (to compare)

---

## Compound Metrics

| Feature | Planning | Implementation | Review | Compound | Total | Patterns |
|---------|----------|----------------|--------|----------|-------|----------|
| snaapi-refactor-phase1 | 3h | 5h | 1h | 1h | 10h | 3 new, 4 anti-patterns |

**Trend**: First compound capture. Future work should be significantly faster.

---

## Next Compound Capture

After implementing pragmatic refactor v2, capture:
- Did PHPDoc approach work?
- Was Image Sizes extraction smooth?
- Any new patterns discovered?
