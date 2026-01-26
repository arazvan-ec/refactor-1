# Alternativas al Patrón Saga para SNAAPI

**Feature**: snaapi-improvements-v2
**Created**: 2026-01-26
**Status**: PLANNING - ALTERNATIVES

---

## Por qué NO usar Saga en SNAAPI

Después de analizar el codebase, el **patrón Saga NO es la mejor opción** para SNAAPI por las siguientes razones:

### 1. SNAAPI es un API Gateway, NO un Sistema Transaccional

| Característica | Sistema Transaccional | API Gateway (SNAAPI) |
|----------------|----------------------|----------------------|
| Operación | Escribe datos | Solo lee datos |
| Consistencia | Requiere ACID | Eventual/Best-effort |
| Rollback | Necesario | No aplica |
| Compensación | Crítica | Innecesaria |

> **Insight**: Saga está diseñado para transacciones distribuidas con escrituras. SNAAPI solo **agrega datos de lectura** de múltiples servicios.

### 2. Complejidad Innecesaria

```
Saga añade:
- Compensating transactions (no hay qué compensar en lecturas)
- Saga orchestrator/choreography (sobrecarga para queries)
- Saga log/state machine (innecesario sin transacciones)

SNAAPI necesita:
- Agregación eficiente de respuestas
- Tolerancia a fallos parciales
- Respuestas rápidas con degradación graceful
```

### 3. Opinión de la Industria

> *"If you need distributed transactions across a few microservices, most likely you incorrectly defined and separated domains."* - [DEV Community](https://dev.to/siy/the-saga-is-antipattern-1354)

---

## Alternativas Recomendadas

### Opción A: API Composition Pattern + Resilience Patterns (RECOMENDADA)

**Descripción**: Usar el patrón de [API Composition](https://microservices.io/patterns/data/api-composition.html) con patrones de resiliencia añadidos.

```
┌─────────────────────────────────────────────────────────────────┐
│                     API Composer                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Parallel Fetcher Layer                      │   │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐       │   │
│  │  │Editorial│ │Multimedia│ │  Tags   │ │ Legacy  │       │   │
│  │  │ Fetcher │ │ Fetcher  │ │ Fetcher │ │ Client  │       │   │
│  │  └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘       │   │
│  └───────┼──────────┼──────────┼──────────┼────────────────┘   │
│          │          │          │          │                     │
│  ┌───────▼──────────▼──────────▼──────────▼────────────────┐   │
│  │              Resilience Layer                            │   │
│  │  • Circuit Breaker  • Timeout  • Fallback  • Retry      │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│  ┌───────────────────────────▼─────────────────────────────┐   │
│  │              Aggregator Layer                            │   │
│  │  • Merge responses  • Handle partial failures           │   │
│  │  • Add metadata     • Transform to response format      │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

**Ventajas:**
- Simple y directo para lectura de datos
- Patrones bien establecidos (Circuit Breaker, Timeout, Fallback)
- Permite respuestas parciales naturalmente
- Menor complejidad que Saga

**Implementación:**
```php
// Composer ejecuta en paralelo con fallbacks
$results = $this->parallelFetcher->fetchAll([
    'editorial' => fn() => $this->editorialClient->find($id),
    'multimedia' => fn() => $this->multimediaClient->find($id),
    'tags' => fn() => $this->tagClient->findByEditorial($id),
]);

// Aggregator combina resultados (incluso parciales)
return $this->aggregator->compose($results);
```

---

### Opción B: Backend for Frontend (BFF) Pattern

**Descripción**: Crear un backend dedicado para la app móvil según el [BFF Pattern](https://samnewman.io/patterns/architectural/bff/).

```
┌─────────────────────────────────────────────────────────────────┐
│                    Mobile BFF                                    │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Mobile-Optimized Response                                │  │
│  │  • Reduced payload size                                   │  │
│  │  • Pre-computed image URLs                                │  │
│  │  • Cached aggregations                                    │  │
│  └──────────────────────────────────────────────────────────┘  │
│                              │                                  │
│  ┌──────────────────────────▼───────────────────────────────┐  │
│  │  Aggregation Layer                                        │  │
│  │  (Current orchestrator logic)                             │  │
│  └──────────────────────────────────────────────────────────┘  │
│                              │                                  │
│         ┌────────────────────┼────────────────────┐            │
│         ▼                    ▼                    ▼            │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐       │
│  │  Editorial  │    │  Multimedia │    │    Tags     │       │
│  │   Service   │    │   Service   │    │   Service   │       │
│  └─────────────┘    └─────────────┘    └─────────────┘       │
└─────────────────────────────────────────────────────────────────┘
```

**Ventajas:**
- Optimizado para necesidades móviles
- Puede pre-computar y cachear agregaciones
- Separa concerns de web vs mobile
- [Mejora 30-60% en tiempos de carga](https://alokai.com/blog/backend-for-frontend)

**SNAAPI ya es un BFF** - Solo necesita optimizaciones de resiliencia.

---

### Opción C: Reactive Streams / Event-Driven

**Descripción**: Usar programación reactiva para composición asíncrona sin bloqueos.

```php
// Usando ReactPHP o similar
$editorial$ = $this->editorialClient->findAsync($id);
$multimedia$ = $this->multimediaClient->findAsync($id);
$tags$ = $this->tagClient->findAsync($id);

// Combinar streams reactivamente
return Observable::zip([$editorial$, $multimedia$, $tags$])
    ->map(fn($results) => $this->aggregator->compose($results))
    ->timeout(5000)
    ->onErrorReturn($this->fallbackResponse());
```

**Ventajas:**
- No-blocking I/O
- Backpressure handling
- Natural timeout/error handling

**Desventajas:**
- Requiere cambio de paradigma
- Curva de aprendizaje
- Symfony no es nativo reactivo (necesita ReactPHP/Amp)

---

## Comparación de Alternativas

| Criterio | Saga | API Composition | BFF | Reactive |
|----------|------|-----------------|-----|----------|
| Complejidad | Alta | Baja | Media | Media |
| Fit para SNAAPI | ❌ Pobre | ✅ Excelente | ✅ Bueno | ⚠️ Medio |
| Respuestas parciales | ✅ | ✅ | ✅ | ✅ |
| Compensación/Rollback | ✅ | ❌ No necesario | ❌ | ❌ |
| Tiempo implementación | Alto | Bajo | Medio | Alto |
| Curva aprendizaje | Alta | Baja | Baja | Alta |

---

## Recomendación Final: API Composition + Resilience

### Arquitectura Propuesta

```
┌─────────────────────────────────────────────────────────────────┐
│                     EditorialController                          │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                  EditorialComposer (nuevo)                       │
│                                                                  │
│  Responsabilidades:                                             │
│  1. Orquestar llamadas paralelas                                │
│  2. Aplicar timeouts por servicio                               │
│  3. Manejar fallbacks                                           │
│  4. Componer respuesta final                                    │
└─────────────────────────────┬───────────────────────────────────┘
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ CircuitBreaker  │ │ CircuitBreaker  │ │ CircuitBreaker  │
│   + Timeout     │ │   + Timeout     │ │   + Timeout     │
│   + Fallback    │ │   + Fallback    │ │   + Fallback    │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ EditorialClient │ │MultimediaClient │ │   TagClient     │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

### Componentes a Implementar

#### 1. Parallel Fetcher (Prioridad ALTA)
```php
interface ParallelFetcherInterface
{
    /**
     * Execute multiple operations in parallel with individual timeouts.
     *
     * @param array<string, callable> $operations
     * @param array<string, int> $timeouts Timeout per operation in ms
     * @return FetchResult Contains successful and failed results
     */
    public function fetchAll(array $operations, array $timeouts = []): FetchResult;
}
```

#### 2. Circuit Breaker (Prioridad ALTA)
```php
interface CircuitBreakerInterface
{
    public function execute(string $service, callable $operation): mixed;
    public function isOpen(string $service): bool;
    public function recordSuccess(string $service): void;
    public function recordFailure(string $service): void;
}
```

#### 3. Fallback Registry (Prioridad MEDIA)
```php
interface FallbackRegistryInterface
{
    public function register(string $service, callable $fallback): void;
    public function getFallback(string $service): ?callable;
    public function hasFallback(string $service): bool;
}
```

#### 4. Response Composer (Prioridad ALTA)
```php
interface ResponseComposerInterface
{
    /**
     * Compose final response from partial results.
     *
     * @param FetchResult $results Contains successes and failures
     * @return ComposedResponse Includes data and metadata about failures
     */
    public function compose(FetchResult $results): ComposedResponse;
}
```

---

## Plan de Implementación (Sin Saga)

### Fase 1: Parallel Fetching (2 días)
| Tarea | Descripción |
|-------|-------------|
| PF-001 | Crear ParallelFetcher interface e implementación |
| PF-002 | Crear FetchResult DTO con successes/failures |
| PF-003 | Migrar tags fetching a paralelo |
| PF-004 | Migrar photos fetching a paralelo |
| PF-005 | Tests y benchmarks |

### Fase 2: Circuit Breaker (2 días)
| Tarea | Descripción |
|-------|-------------|
| CB-001 | Crear CircuitBreaker interface |
| CB-002 | Implementar InMemoryCircuitBreaker |
| CB-003 | Crear service configuration |
| CB-004 | Decorar external clients |
| CB-005 | Tests de failure scenarios |

### Fase 3: Graceful Degradation (2 días)
| Tarea | Descripción |
|-------|-------------|
| GD-001 | Crear FallbackRegistry |
| GD-002 | Definir fallbacks por servicio |
| GD-003 | Crear ResponseComposer |
| GD-004 | Añadir metadata de failures a response |
| GD-005 | Tests de partial responses |

### Fase 4: Observability (1 día)
| Tarea | Descripción |
|-------|-------------|
| OB-001 | Añadir correlation IDs |
| OB-002 | Structured logging |
| OB-003 | Metrics collection |

---

## Ejemplo de Response con Graceful Degradation

```json
{
  "data": {
    "id": "editorial-123",
    "title": "Article Title",
    "body": [...],
    "multimedia": null,
    "tags": ["tag1", "tag2"],
    "signatures": [...]
  },
  "meta": {
    "partial": true,
    "degraded_services": [
      {
        "service": "multimedia",
        "reason": "timeout",
        "fallback_used": true
      }
    ],
    "response_time_ms": 245
  }
}
```

---

## Conclusión

**NO usar Saga** para SNAAPI porque:
1. Es un patrón para **transacciones de escritura**, no lectura
2. Añade complejidad innecesaria (compensating transactions)
3. SNAAPI ya implementa el patrón correcto (API Composition/BFF)

**SÍ usar API Composition + Resilience Patterns** porque:
1. Es el patrón estándar para agregación de datos
2. Permite respuestas parciales naturalmente
3. Menor complejidad, mayor mantenibilidad
4. Patrones de resiliencia (Circuit Breaker, Timeout, Fallback) resuelven los problemas identificados

---

## Referencias

- [API Composition Pattern - Microservices.io](https://microservices.io/patterns/data/api-composition.html)
- [Backend for Frontend Pattern - Sam Newman](https://samnewman.io/patterns/architectural/bff/)
- [API Gateway Aggregation Pattern - DEV Community](https://dev.to/vaib/boost-performance-simplify-microservices-the-api-gateway-aggregation-pattern-52hi)
- [Circuit Breaker Pattern - Microsoft](https://learn.microsoft.com/en-us/azure/architecture/patterns/circuit-breaker)
- [Graceful Degradation in Microservices - GeeksforGeeks](https://www.geeksforgeeks.org/system-design/api-composition-pattern-in-microservices/)
- [The Saga is Antipattern - DEV Community](https://dev.to/siy/the-saga-is-antipattern-1354)
- [BFF Pattern Best Practices - Alokai](https://alokai.com/blog/backend-for-frontend)
