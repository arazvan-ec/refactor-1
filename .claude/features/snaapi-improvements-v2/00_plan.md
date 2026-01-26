# Plan de Mejoras: SNAAPI v2

**Feature**: snaapi-improvements-v2
**Created**: 2026-01-26
**Status**: PLANNING

---

## Resumen Ejecutivo

Basado en el análisis exhaustivo del codebase, se identificaron las siguientes áreas de mejora:

| Área | Prioridad | Impacto | Esfuerzo |
|------|-----------|---------|----------|
| Saga Pattern | ALTA | Resiliencia | MEDIO |
| Parallelization | ALTA | Performance | BAJO |
| Circuit Breaker | MEDIA | Resiliencia | MEDIO |
| Observability | MEDIA | Debugging | BAJO |
| Type Safety | BAJA | Mantenibilidad | MEDIO |

---

## 1. SAGA PATTERN

### Problema Actual
El flujo de construcción de respuesta editorial es una **transacción larga** con múltiples llamadas remotas sin coordinación de errores:

```
fetch_editorial() → fetch_embedded() → fetch_tags() → resolve_promises() → aggregate()
```

**Problemas:**
- Si un paso falla parcialmente, los siguientes continúan con datos incompletos
- No hay mecanismo de rollback o compensación
- No hay timeout management por paso
- Recursos desperdiciados si un paso tardío falla

### Solución Propuesta

#### 1.1 Interface EditorialSaga

```php
namespace App\Application\Saga;

interface EditorialSagaInterface
{
    /**
     * Execute the saga with compensation support.
     *
     * @return EditorialSagaResult Contains response or partial failures
     */
    public function execute(string $editorialId): EditorialSagaResult;

    /**
     * Get current step for monitoring.
     */
    public function getCurrentStep(): SagaStep;

    /**
     * Cancel ongoing operations.
     */
    public function cancel(): void;
}
```

#### 1.2 Saga Steps Definition

```php
namespace App\Application\Saga\Step;

enum SagaStep: string
{
    case FETCH_EDITORIAL = 'fetch_editorial';
    case CHECK_LEGACY = 'check_legacy';
    case FETCH_EMBEDDED = 'fetch_embedded';
    case FETCH_TAGS = 'fetch_tags';
    case FETCH_MEMBERSHIP = 'fetch_membership';
    case RESOLVE_MULTIMEDIA = 'resolve_multimedia';
    case FETCH_BODY_PHOTOS = 'fetch_body_photos';
    case AGGREGATE_RESPONSE = 'aggregate_response';
}
```

#### 1.3 Step Handler Interface

```php
namespace App\Application\Saga\Step;

interface SagaStepHandlerInterface
{
    /**
     * Execute the step.
     *
     * @throws SagaStepException On failure
     */
    public function execute(SagaContext $context): SagaContext;

    /**
     * Compensate for this step (rollback).
     */
    public function compensate(SagaContext $context): void;

    /**
     * Check if step can be skipped.
     */
    public function canSkip(SagaContext $context): bool;

    /**
     * Get step timeout in milliseconds.
     */
    public function getTimeout(): int;
}
```

#### 1.4 Saga Context (State Carrier)

```php
namespace App\Application\Saga;

final class SagaContext
{
    public function __construct(
        public readonly string $editorialId,
        public readonly string $correlationId,
        public ?FetchedEditorialDTO $editorial = null,
        public ?EmbeddedContentDTO $embeddedContent = null,
        public array $tags = [],
        public array $resolvedMultimedia = [],
        public array $membershipLinks = [],
        public array $bodyPhotos = [],
        public array $failures = [],
        public array $skippedSteps = [],
    ) {}

    public function withEditorial(FetchedEditorialDTO $editorial): self;
    public function withFailure(SagaStep $step, \Throwable $error): self;
    public function hasFailures(): bool;
    public function isPartialSuccess(): bool;
}
```

#### 1.5 Saga Result

```php
namespace App\Application\Saga;

final readonly class EditorialSagaResult
{
    public function __construct(
        public bool $success,
        public ?array $response,
        public array $failures,
        public array $metrics,
        public string $correlationId,
    ) {}

    public function isPartialSuccess(): bool
    {
        return $this->success && !empty($this->failures);
    }
}
```

### Implementación

**Archivos a crear:**
- `src/Application/Saga/EditorialSagaInterface.php`
- `src/Application/Saga/EditorialSaga.php`
- `src/Application/Saga/SagaContext.php`
- `src/Application/Saga/EditorialSagaResult.php`
- `src/Application/Saga/Step/SagaStepHandlerInterface.php`
- `src/Application/Saga/Step/SagaStep.php` (enum)
- `src/Application/Saga/Step/FetchEditorialStep.php`
- `src/Application/Saga/Step/FetchEmbeddedContentStep.php`
- `src/Application/Saga/Step/FetchTagsStep.php`
- `src/Application/Saga/Step/ResolveMutimediaStep.php`
- `src/Application/Saga/Step/AggregateResponseStep.php`
- `src/Application/Saga/Exception/SagaStepException.php`
- `src/Application/Saga/Exception/SagaTimeoutException.php`

---

## 2. PARALLELIZATION

### Problema Actual
Operaciones síncronas bloquean el flujo async:

```php
// EditorialOrchestrator.php - Bloquea mientras busca tags
foreach ($editorial->tags()->getArrayCopy() as $tag) {
    $tags[] = $this->queryTagClient->findTagById($tag->id()); // BLOCKING!
}

// También bloquea para fotos del body
foreach ($arrayOfBodyTagPicture as $bodyTagPicture) {
    $photo = $this->queryMultimediaClient->findPhotoById($id); // BLOCKING!
}
```

### Solución Propuesta

#### 2.1 Parallel Tag Fetcher

```php
namespace App\Application\Service\Tag;

interface ParallelTagFetcherInterface
{
    /**
     * Fetch multiple tags in parallel.
     *
     * @param array<string> $tagIds
     * @return array<string, Tag> Keyed by tag ID
     */
    public function fetchAll(array $tagIds): array;
}
```

```php
final class ParallelTagFetcher implements ParallelTagFetcherInterface
{
    public function fetchAll(array $tagIds): array
    {
        $promises = [];
        foreach ($tagIds as $tagId) {
            $promises[$tagId] = $this->queryTagClient->findTagById($tagId, async: true);
        }

        return Utils::settle($promises)
            ->then($this->resolveTagPromises(...))
            ->wait(true);
    }
}
```

#### 2.2 Parallel Photo Fetcher

```php
namespace App\Application\Service\Multimedia;

interface ParallelPhotoFetcherInterface
{
    /**
     * Fetch multiple photos in parallel.
     *
     * @param array<string> $photoIds
     * @return array<string, Photo> Keyed by photo ID
     */
    public function fetchAll(array $photoIds): array;
}
```

### Impacto Esperado

| Métrica | Antes | Después | Mejora |
|---------|-------|---------|--------|
| Tags fetch (10 tags) | ~1000ms | ~150ms | 85% |
| Photos fetch (5 photos) | ~500ms | ~120ms | 76% |
| Total response time | ~2000ms | ~800ms | 60% |

---

## 3. CIRCUIT BREAKER

### Problema Actual
No hay protección contra servicios externos lentos o caídos:

```php
// Si el legacy client tarda 30s, toda la respuesta tarda 30s
$comments = $this->queryLegacyClient->findCommentsByEditorialId($id);
```

### Solución Propuesta

#### 3.1 Circuit Breaker Interface

```php
namespace App\Infrastructure\CircuitBreaker;

interface CircuitBreakerInterface
{
    public function call(callable $operation, string $serviceName): mixed;
    public function getState(string $serviceName): CircuitState;
    public function reset(string $serviceName): void;
}

enum CircuitState: string
{
    case CLOSED = 'closed';      // Normal operation
    case OPEN = 'open';          // Failing, skip calls
    case HALF_OPEN = 'half_open'; // Testing recovery
}
```

#### 3.2 Configuration

```yaml
# config/packages/circuit_breaker.yaml
circuit_breaker:
    services:
        legacy_client:
            failure_threshold: 5
            success_threshold: 3
            timeout: 30000  # 30 seconds
            retry_timeout: 60000  # 1 minute before retry

        editorial_client:
            failure_threshold: 3
            timeout: 5000

        multimedia_client:
            failure_threshold: 5
            timeout: 10000
```

#### 3.3 Decorator Pattern

```php
namespace App\Infrastructure\CircuitBreaker;

final class CircuitBreakerClientDecorator
{
    public function __construct(
        private readonly object $client,
        private readonly CircuitBreakerInterface $circuitBreaker,
        private readonly string $serviceName,
    ) {}

    public function __call(string $method, array $arguments): mixed
    {
        return $this->circuitBreaker->call(
            fn() => $this->client->$method(...$arguments),
            $this->serviceName
        );
    }
}
```

---

## 4. OBSERVABILITY

### Problema Actual
- Sin correlation IDs entre servicios
- Logs dispersos sin contexto
- Difícil rastrear flujo de request

### Solución Propuesta

#### 4.1 Request Context

```php
namespace App\Infrastructure\Context;

final class RequestContext
{
    public function __construct(
        public readonly string $correlationId,
        public readonly string $editorialId,
        public readonly float $startTime,
        public readonly array $metadata = [],
    ) {}

    public static function create(string $editorialId): self
    {
        return new self(
            correlationId: Uuid::v4()->toRfc4122(),
            editorialId: $editorialId,
            startTime: microtime(true),
        );
    }
}
```

#### 4.2 Structured Logging

```php
namespace App\Infrastructure\Logging;

interface StructuredLoggerInterface
{
    public function logStep(
        RequestContext $context,
        string $step,
        string $status,
        array $data = [],
    ): void;

    public function logError(
        RequestContext $context,
        string $step,
        \Throwable $error,
    ): void;
}
```

#### 4.3 Log Format

```json
{
    "timestamp": "2026-01-26T10:30:00Z",
    "correlation_id": "abc-123-def",
    "editorial_id": "edit-456",
    "step": "fetch_tags",
    "status": "completed",
    "duration_ms": 145,
    "data": {
        "tags_count": 5,
        "failed_tags": 0
    }
}
```

---

## 5. TYPE SAFETY (Mejoras adicionales)

### Problema Actual
Muchos métodos retornan `array<string, mixed>` sin tipado fuerte.

### DTOs Propuestos

```php
// Intermediate DTOs for saga steps
namespace App\Application\DTO\Saga;

final readonly class TagsResultDTO
{
    public function __construct(
        public array $tags,
        public array $failedIds,
        public int $fetchDurationMs,
    ) {}
}

final readonly class MultimediaResultDTO
{
    public function __construct(
        public array $resolved,
        public array $failed,
        public int $totalPromises,
        public int $resolvedCount,
    ) {}
}
```

---

## Fases de Implementación

### Fase 1: Parallelization (Quick Win)
**Duración estimada**: 1-2 días
**Impacto**: Alto (mejora de performance inmediata)

| Tarea | Descripción |
|-------|-------------|
| PAR-001 | Crear ParallelTagFetcher |
| PAR-002 | Crear ParallelPhotoFetcher |
| PAR-003 | Integrar en EditorialOrchestrator |
| PAR-004 | Tests y benchmarking |

### Fase 2: Observability
**Duración estimada**: 1 día
**Impacto**: Medio (mejor debugging)

| Tarea | Descripción |
|-------|-------------|
| OBS-001 | Crear RequestContext |
| OBS-002 | Crear StructuredLogger |
| OBS-003 | Integrar correlation IDs |
| OBS-004 | Actualizar logs existentes |

### Fase 3: Circuit Breaker
**Duración estimada**: 2-3 días
**Impacto**: Alto (resiliencia)

| Tarea | Descripción |
|-------|-------------|
| CB-001 | Crear CircuitBreaker interface |
| CB-002 | Implementar RedisCircuitBreaker |
| CB-003 | Crear ClientDecorator |
| CB-004 | Configurar per-service settings |
| CB-005 | Tests con failure scenarios |

### Fase 4: Saga Pattern
**Duración estimada**: 3-5 días
**Impacto**: Alto (resiliencia y mantenibilidad)

| Tarea | Descripción |
|-------|-------------|
| SAGA-001 | Crear interfaces base |
| SAGA-002 | Implementar SagaContext |
| SAGA-003 | Crear step handlers |
| SAGA-004 | Implementar EditorialSaga |
| SAGA-005 | Integrar con orchestrator |
| SAGA-006 | Tests de compensation |
| SAGA-007 | Metrics y monitoring |

---

## Diagrama de Arquitectura Propuesta

```
┌─────────────────────────────────────────────────────────────────┐
│                         Controller                               │
│                    EditorialController                           │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      EditorialSaga                               │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐           │
│  │ Step 1  │→│ Step 2  │→│ Step 3  │→│ Step N  │            │
│  │ Fetch   │  │ Embedded│  │ Tags    │  │Aggregate│            │
│  └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘           │
│       │            │            │            │                  │
│       ▼            ▼            ▼            ▼                  │
│  [Compensate] [Compensate] [Compensate] [Compensate]           │
└─────────────────────────────┬───────────────────────────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ CircuitBreaker  │ │ CircuitBreaker  │ │ CircuitBreaker  │
│ (Editorial)     │ │ (Multimedia)    │ │ (Legacy)        │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ QueryEditorial  │ │ QueryMultimedia │ │ QueryLegacy     │
│ Client          │ │ Client          │ │ Client          │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

---

## Métricas de Éxito

| Métrica | Actual | Objetivo |
|---------|--------|----------|
| Response time p95 | ~2000ms | <800ms |
| Error rate | ~2% | <0.5% |
| Timeout failures | No tracking | <0.1% |
| Recovery time | N/A | <5min |
| Log correlation | 0% | 100% |

---

## Conclusión

El patrón **Saga** es altamente recomendado para este proyecto porque:

1. **Múltiples servicios externos**: Editorial, Multimedia, Tags, Membership, Legacy
2. **Transacciones largas**: La respuesta requiere coordinar 6-8 llamadas
3. **Tolerancia a fallos**: Necesitamos manejar fallos parciales gracefully
4. **Observabilidad**: Cada paso puede ser monitoreado y medido
5. **Compensación**: Podemos limpiar recursos si un paso falla

Sin embargo, **empezar con Parallelization** (Fase 1) da resultados inmediatos con menos esfuerzo, y prepara el código para la implementación del Saga.
