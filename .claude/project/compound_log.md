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

---

## 2026-01-27: Architecture Enforcement - Transformation Layer Purity

### Summary

Detectado y corregido violación arquitectónica: ResponseAggregator hacía llamadas HTTP cuando solo debería transformar datos. Implementado mecanismo de validación automática.

**Resultado clave**: Creado test de arquitectura que previene futuras violaciones + refactorizado ResponseAggregator para cumplir con principio de responsabilidad única.

### Time Investment
- Detection: 5 min (user feedback)
- Analysis: 10 min
- Implementation: 30 min
- Testing: 10 min
- Compound: 15 min
- **Total**: ~1 hour

### The 70% Boundary Analysis

#### Where did the 70% end?
**Milestone**: Architecture test created, interfaces defined
**Time spent**: 20 min (33% of total)

#### What made the 30% hard?
1. **Wiring new services**
   - Symfony service configuration needed updates
   - Interface bindings for DI
   - **Lección**: Siempre verificar `config/packages/` después de crear servicios

2. **Preserving JournalistsDataTransformer dependency**
   - SignatureFetcher necesita el transformer para formatear
   - Transformer está en Application layer, Fetcher en Orchestrator
   - **Lección**: Los Fetchers pueden usar Transformers, pero no al revés

### Patterns to Reuse (HIGH VALUE)

#### Pattern 4: Architecture Validation Tests
**Where**: `tests/Architecture/TransformationLayerArchitectureTest.php`
**Why it works**:
- Detecta violaciones automáticamente en CI
- Usa reflection para inspeccionar constructores
- Falla con mensaje claro explicando la violación
- Corre con `--group architecture`

**Recomendación**: Crear tests de arquitectura para cada restricción de capas
```php
// Pattern para detectar dependencias prohibidas
foreach ($constructor->getParameters() as $param) {
    $typeName = $param->getType()->getName();
    if ($this->isForbiddenDependency($typeName)) {
        $this->fail("Layer violation: {$className} injects {$typeName}");
    }
}
```

#### Pattern 5: Pre-Fetched Data DTO
**Where**: `src/Application/DTO/PreFetchedDataDTO.php`
**Why it works**:
- Separa claramente "quién fetch" de "quién transforma"
- DTO inmutable con datos ya resueltos
- Elimina la tentación de hacer HTTP en capas incorrectas

**Recomendación**: Cuando una capa de transformación necesita datos externos:
```
INCORRECTO:
Transformer → HTTP Client → External Service

CORRECTO:
Orchestrator → HTTP Client → External Service
           ↓
    PreFetchedDataDTO
           ↓
       Transformer
```

#### Pattern 6: Service Extraction to Correct Layer
**Where**: `src/Orchestrator/Service/SignatureFetcher.php`
**Why it works**:
- Servicio vive donde debe hacer HTTP (Orchestrator layer)
- Usa transformer para formato (no duplica lógica)
- Interface permite testing fácil

**Template**:
```php
// Orchestrator layer - CAN make HTTP calls
final class DataFetcher implements DataFetcherInterface
{
    public function __construct(
        private readonly HttpClient $client,      // ✅ OK here
        private readonly DataTransformer $transformer, // For formatting
    ) {}

    public function fetch(): FormattedData
    {
        $raw = $this->client->get();  // HTTP call in correct layer
        return $this->transformer->format($raw);
    }
}
```

### Anti-Patterns Documented (AVOID)

#### Anti-Pattern 5: HTTP Calls in Transformation Layer
**Example**: ResponseAggregator tenía QueryLegacyClient y QueryJournalistClient
**Problem**:
- Viola Single Responsibility Principle
- Dificulta testing (necesita mocks de HTTP)
- Oculta latencia de red en "transformers"
- Hace imposible paralelizar fetches

**Symptoms**:
```php
// RED FLAG: Transformer/Aggregator con "Client" en constructor
class ResponseAggregator {
    public function __construct(
        private QueryLegacyClient $client,  // ❌ VIOLATION
    ) {}
}
```

**Rule**: Clases en `Application\DataTransformer` y `Application\Service\*Aggregator`:
- NO pueden inyectar *Client
- NO pueden hacer llamadas HTTP
- Solo reciben datos ya fetched como parámetros

### Rules Updated

#### Addition to project_specific.md
```markdown
## Layer Purity Rules

### Transformation Layer (DataTransformers, Aggregators)
- ❌ NO HTTP clients injected
- ❌ NO network calls
- ✅ Only transform data structures
- ✅ Receive pre-fetched data as parameters

### Orchestrator Layer (Orchestrators, Fetchers)
- ✅ CAN inject HTTP clients
- ✅ CAN make network calls
- ✅ Coordinates fetching and transformation
- ✅ Passes pre-fetched data to transformers

### Validation
Run architecture tests: `./bin/phpunit --group architecture`
```

### Impact on Future Work

#### Immediate benefits:
- ResponseAggregator ahora testeable sin mocks HTTP
- Fácil paralelizar fetches en Orchestrator
- Clara separación de responsabilidades

#### For next refactor:
- Revisar otros Transformers por violaciones similares
- Considerar extender ArchitectureTest a más capas
- Documentar patrones de capas en CLAUDE.md

### Files Reference

**Created:**
- `tests/Architecture/TransformationLayerArchitectureTest.php` - Validador
- `src/Application/DTO/PreFetchedDataDTO.php` - DTO para datos pre-fetched
- `src/Orchestrator/Service/SignatureFetcher.php` - Extrae HTTP a capa correcta
- `src/Orchestrator/Service/CommentsFetcher.php` - Extrae HTTP a capa correcta

**Modified:**
- `src/Application/Service/Editorial/ResponseAggregator.php` - Limpio, sin HTTP
- `src/Orchestrator/Chain/EditorialOrchestrator.php` - Usa nuevos fetchers
- `config/packages/orchestrators.yaml` - Service wiring

---

## Compound Metrics (Updated)

| Feature | Planning | Implementation | Review | Compound | Total | Patterns |
|---------|----------|----------------|--------|----------|-------|----------|
| snaapi-refactor-phase1 | 3h | 5h | 1h | 1h | 10h | 3 new, 4 anti-patterns |
| pragmatic-refactor-v1 | 0.5h | 1h | 0.25h | 0.25h | 2h | PHPDoc shapes, Config extraction |
| architecture-enforcement | 0.25h | 0.5h | 0.1h | 0.25h | 1.1h | 3 new, 1 anti-pattern fixed |

**Trend**: Trabajo más enfocado = ciclos más cortos. Patterns compound acelerando.

---

## Next Compound Capture

Después de QA approval, capturar:
- ¿El architecture test detectó algún false positive?
- ¿Se necesitan más capas validadas?
- ¿Hubo resistencia al nuevo patrón PreFetchedDataDTO?
