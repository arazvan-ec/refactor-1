# Especificaciones Técnicas: Paralelización en SNAAPI

**Proyecto**: SNAAPI - API Gateway
**Fecha**: 2026-01-26
**Versión**: 1.0

---

## 1. PREGUNTA: ¿Usar PHP Fibers?

### Respuesta: NO es necesario

**Razón**: SNAAPI ya usa **Guzzle Promises** que proporcionan concurrencia HTTP nativa.

| Tecnología | Estado en SNAAPI | Recomendación |
|------------|------------------|---------------|
| Guzzle Promises | ✅ Ya implementado | **Extender uso** |
| PHP Fibers | ❌ No necesario | No añade valor |
| Amp/ReactPHP | ❌ No necesario | Sobrecarga innecesaria |

### Justificación Técnica

```
┌─────────────────────────────────────────────────────────────────┐
│                    PHP Fibers vs Guzzle Promises                 │
├─────────────────────────────────────────────────────────────────┤
│ PHP Fibers:                                                      │
│ - Low-level API (no usar directamente en aplicación)            │
│ - Requiere event loop externo (Amp, ReactPHP)                   │
│ - Útil para I/O mixto (DB + HTTP + Files)                       │
│ - Sobrecarga para solo HTTP                                      │
├─────────────────────────────────────────────────────────────────┤
│ Guzzle Promises (YA EN SNAAPI):                                 │
│ - Alto nivel, fácil de usar                                      │
│ - Optimizado para HTTP concurrente                              │
│ - Utils::settle() para batch resolution                         │
│ - Ya integrado en el codebase                                   │
└─────────────────────────────────────────────────────────────────┘
```

> *"If the need for asynchronous functionality is limited to sending HTTP requests, then using Guzzle with its asynchronous request feature is likely the best option."* - [PHP Async Comparison](https://gorannikolovski.com/blog/asynchronous-and-concurrent-http-requests-in-php)

---

## 2. ESTADO ACTUAL: Análisis de Operaciones

### Operaciones Actuales (15 total)

| # | Servicio | Operación | Estado | Impacto |
|---|----------|-----------|--------|---------|
| 1 | Editorial | findEditorialById | SYNC | Crítico |
| 2 | Section | findSectionById | SYNC | Crítico |
| 3 | Multimedia | findMultimediaById (embedded) | ✅ ASYNC | OK |
| 4 | Multimedia | findMultimediaById (opening) | SYNC | Mejorable |
| 5 | Multimedia | findMultimediaById (meta) | SYNC | Mejorable |
| 6 | Photo | findPhotoById (body) | **SYNC LOOP** | ⚠️ N+1 |
| 7 | Photo | findPhotoById (embedded) | SYNC | Mejorable |
| 8 | **Tag** | **findTagById** | **SYNC LOOP** | ⚠️⚠️ **CRÍTICO** |
| 9 | **Journalist** | **findJournalistByAliasId** (embedded) | **SYNC LOOP** | ⚠️⚠️ **CRÍTICO** |
| 10 | **Journalist** | **findJournalistByAliasId** (response) | **SYNC LOOP** | ⚠️⚠️ **DUPLICADO** |
| 11 | Membership | getMembershipUrl | ✅ ASYNC | OK |
| 12 | Comments | findCommentsByEditorialId | SYNC | Mejorable |
| 13 | Legacy | findEditorialById | SYNC | Fallback |
| 14 | Legacy | findCommentsByEditorialId | SYNC | Fallback |
| 15 | Widget | fetchWidget | SYNC | Mejorable |

### Diagnóstico de Performance

```
ACTUAL (secuencial):
Editorial (2s) → Section (2s) → Embedded (4s) → Tags LOOP (2s×N) → Photos LOOP (2s×P)
                                                      ↑                    ↑
                                                   CUELLO DE            CUELLO DE
                                                   BOTELLA              BOTELLA

Editorial con 5 tags + 3 fotos = 2 + 2 + 4 + 10 + 6 = 24 segundos
```

---

## 3. SOLUCIÓN RECOMENDADA: Parallel Fetcher Pattern

### Patrón: Promise Collection + Batch Resolution

Este patrón ya existe en SNAAPI (`PromiseResolver`), solo necesita **extenderse**.

```php
// PATRÓN ACTUAL (solo multimedia)
$promises = [];
foreach ($multimedias as $m) {
    $promises[] = $client->findMultimediaById($m->id(), async: true);
}
$results = Utils::settle($promises)->wait(true);

// PATRÓN EXTENDIDO (tags, journalists, photos)
$tagPromises = [];
foreach ($tagIds as $tagId) {
    $tagPromises[$tagId] = $tagClient->findTagById($tagId, async: true);
}
$tags = Utils::settle($tagPromises)->wait(true);
```

### Arquitectura Propuesta

```
┌─────────────────────────────────────────────────────────────────┐
│                     ParallelFetcher                              │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  Promise Collector                                         │  │
│  │  - Recoge promesas de múltiples fuentes                   │  │
│  │  - Agrupa por tipo (tags, photos, journalists)            │  │
│  └───────────────────────────────────────────────────────────┘  │
│                              │                                   │
│  ┌───────────────────────────▼───────────────────────────────┐  │
│  │  Batch Resolver (Utils::settle)                           │  │
│  │  - Ejecuta todas las promesas en paralelo                 │  │
│  │  - Timeout configurable por batch                         │  │
│  │  - Maneja fallos individuales                             │  │
│  └───────────────────────────────────────────────────────────┘  │
│                              │                                   │
│  ┌───────────────────────────▼───────────────────────────────┐  │
│  │  Result Aggregator                                         │  │
│  │  - Combina resultados exitosos                            │  │
│  │  - Registra fallos para degradación graceful              │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 4. ESPECIFICACIÓN DE IMPLEMENTACIÓN

### 4.1 Interface ParallelFetcher

```php
namespace App\Application\Service\Parallel;

interface ParallelFetcherInterface
{
    /**
     * Fetch multiple resources in parallel.
     *
     * @param array<string, callable> $operations Key => async callable
     * @param int $timeoutMs Global timeout for batch
     * @return BatchResult Contains fulfilled and rejected results
     */
    public function fetchAll(array $operations, int $timeoutMs = 5000): BatchResult;

    /**
     * Fetch items of same type in parallel (e.g., all tags).
     *
     * @param array<string> $ids Resource IDs to fetch
     * @param callable $fetcher Function that returns a promise: fn($id) => Promise
     * @param int $timeoutMs Timeout per item
     * @return TypedBatchResult<T>
     */
    public function fetchMany(array $ids, callable $fetcher, int $timeoutMs = 2000): TypedBatchResult;
}
```

### 4.2 BatchResult DTO

```php
namespace App\Application\DTO\Parallel;

final readonly class BatchResult
{
    /**
     * @param array<string, mixed> $fulfilled Successful results keyed by ID
     * @param array<string, FailureInfo> $rejected Failed operations with reason
     * @param int $totalMs Total execution time
     */
    public function __construct(
        public array $fulfilled,
        public array $rejected,
        public int $totalMs,
    ) {}

    public function isComplete(): bool
    {
        return empty($this->rejected);
    }

    public function isPartial(): bool
    {
        return !empty($this->fulfilled) && !empty($this->rejected);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->fulfilled[$key] ?? $default;
    }
}
```

### 4.3 Implementación con Guzzle Promises

```php
namespace App\Application\Service\Parallel;

use GuzzleHttp\Promise\Utils;

final class GuzzleParallelFetcher implements ParallelFetcherInterface
{
    public function fetchMany(array $ids, callable $fetcher, int $timeoutMs = 2000): TypedBatchResult
    {
        if (empty($ids)) {
            return new TypedBatchResult([], [], 0);
        }

        $start = microtime(true);
        $promises = [];

        foreach ($ids as $id) {
            $promises[$id] = $fetcher($id);
        }

        // Ejecutar todas en paralelo
        $results = Utils::settle($promises)->wait(true);

        $fulfilled = [];
        $rejected = [];

        foreach ($results as $id => $result) {
            if ($result['state'] === 'fulfilled') {
                $fulfilled[$id] = $result['value'];
            } else {
                $rejected[$id] = new FailureInfo(
                    id: $id,
                    reason: $result['reason']?->getMessage() ?? 'Unknown error',
                    exception: $result['reason'] ?? null,
                );
            }
        }

        $totalMs = (int)((microtime(true) - $start) * 1000);

        return new TypedBatchResult($fulfilled, $rejected, $totalMs);
    }
}
```

---

## 5. CAMBIOS REQUERIDOS EN CLIENTES

### 5.1 QueryTagClient - Añadir soporte async

```php
// ACTUAL
public function findTagById(string $tagId): Tag;

// PROPUESTO
public function findTagById(string $tagId, bool $async = false): Tag|Promise;
```

### 5.2 QueryJournalistClient - Añadir soporte async

```php
// ACTUAL
public function findJournalistByAliasId(AliasId $aliasId): Journalist;

// PROPUESTO
public function findJournalistByAliasId(AliasId $aliasId, bool $async = false): Journalist|Promise;
```

### 5.3 QueryMultimediaClient - Ya tiene async (verificar photos)

```php
// VERIFICAR que findPhotoById soporte async
public function findPhotoById(string $photoId, bool $async = false): Photo|Promise;
```

---

## 6. REFACTOR DEL ORCHESTRATOR

### Antes (secuencial)

```php
// EditorialOrchestrator.php - ACTUAL
private function fetchTags(Editorial $editorial): array
{
    $tags = [];
    foreach ($editorial->tags()->getArrayCopy() as $tag) {
        try {
            $tags[] = $this->queryTagClient->findTagById($tag->id()); // BLOCKING!
        } catch (\Throwable $e) {
            $this->logger->warning(...);
        }
    }
    return $tags;
}
```

### Después (paralelo)

```php
// EditorialOrchestrator.php - PROPUESTO
private function fetchTags(Editorial $editorial): BatchResult
{
    $tagIds = array_map(
        fn($tag) => $tag->id(),
        $editorial->tags()->getArrayCopy()
    );

    return $this->parallelFetcher->fetchMany(
        ids: $tagIds,
        fetcher: fn($id) => $this->queryTagClient->findTagById($id, async: true),
        timeoutMs: 2000
    );
}
```

---

## 7. IMPACTO EN PERFORMANCE

### Proyección de Mejora

```
ANTES (secuencial):
Tags: 5 × 2s = 10s
Journalists: 3 × 2s = 6s (×2 = 12s por duplicación)
Photos: 3 × 2s = 6s
TOTAL loops: ~28s

DESPUÉS (paralelo):
Tags: max(2s) = 2s (todas en paralelo)
Journalists: max(2s) = 2s (sin duplicación)
Photos: max(2s) = 2s
TOTAL loops: ~6s

MEJORA: 28s → 6s = 78% reducción en loops
```

### Tiempo Total Estimado

| Fase | Antes | Después |
|------|-------|---------|
| Editorial + Section | 4s | 4s (secuencial requerido) |
| Embedded Content | 4s | 2s (más paralelo) |
| Tags | 10s | 2s |
| Journalists | 12s | 2s |
| Photos | 6s | 2s |
| Aggregation | 2s | 2s |
| **TOTAL** | **38s** | **14s** |

---

## 8. CONCLUSIÓN Y DECISIÓN

### Recomendación Final

| Opción | Decisión | Razón |
|--------|----------|-------|
| PHP Fibers | ❌ NO | Guzzle ya provee async HTTP |
| Amp/ReactPHP | ❌ NO | Sobrecarga innecesaria |
| **Guzzle Promises** | ✅ **SÍ** | Ya integrado, solo extender |
| Parallel Fetcher Pattern | ✅ **SÍ** | Encapsula lógica de paralelo |

### Patrón Seleccionado

**Promise Collection + Batch Resolution** usando Guzzle existente.

```
┌──────────────────────────────────────────────────────────────┐
│   NO CAMBIAR:                                                 │
│   - HTTP Client (Guzzle)                                      │
│   - Promise Library (guzzlehttp/promises)                     │
│   - Timeout Configuration (2s)                                │
│                                                               │
│   SÍ CAMBIAR:                                                 │
│   - Añadir async: true a clientes que no lo tienen           │
│   - Crear ParallelFetcher service                            │
│   - Refactorizar loops a batch operations                    │
│   - Añadir BatchResult para manejo de fallos parciales       │
└──────────────────────────────────────────────────────────────┘
```

---

## 9. PLAN DE IMPLEMENTACIÓN

### Fase 1: Infraestructura (1 día)
- [ ] Crear `ParallelFetcherInterface`
- [ ] Crear `GuzzleParallelFetcher`
- [ ] Crear `BatchResult` y `FailureInfo` DTOs
- [ ] Tests unitarios

### Fase 2: Clientes (1 día)
- [ ] Añadir `async` parameter a `QueryTagClient`
- [ ] Añadir `async` parameter a `QueryJournalistClient`
- [ ] Verificar `QueryMultimediaClient::findPhotoById`
- [ ] Tests de integración

### Fase 3: Orchestrator (1 día)
- [ ] Refactorizar `fetchTags()` a paralelo
- [ ] Refactorizar `fetchPhotos()` a paralelo
- [ ] Eliminar duplicación de journalist fetch
- [ ] Tests de regresión

### Fase 4: Resilience (1 día)
- [ ] Añadir Circuit Breaker
- [ ] Configurar timeouts por servicio
- [ ] Implementar fallbacks
- [ ] Tests de failure scenarios

---

## Referencias

- [Guzzle Async Requests](https://docs.guzzlephp.org/en/stable/quickstart.html#concurrent-requests)
- [PHP Fibers - When NOT to use](https://stitcher.io/blog/fibers-with-a-grain-of-salt)
- [Async PHP Comparison 2025](https://gorannikolovski.com/blog/asynchronous-and-concurrent-http-requests-in-php)
- [Guzzle vs Amp Performance](https://medium.com/@RemyTHEROUX/http-concurrency-test-with-php-5435c5079738)
