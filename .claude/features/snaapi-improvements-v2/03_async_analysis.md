# Análisis de Llamadas Async vs Sync

**Proyecto**: SNAAPI
**Fecha**: 2026-01-26
**Versión**: 1.0

---

## Resumen Ejecutivo

| Tipo | Cantidad | Porcentaje |
|------|----------|------------|
| MUST_BE_SYNC (dependencias) | 4 | 17% |
| CURRENTLY_SYNC (parallelizable) | 15 | 63% |
| ALREADY_ASYNC | 3 | 13% |
| LOCAL (sin llamadas externas) | 2 | 8% |
| **TOTAL** | **24** | 100% |

**Oportunidad**: 15 llamadas pueden ser parallelizadas (63% del total)

---

## 1. DIAGRAMA DE DEPENDENCIAS

```
                         ┌─────────────────┐
                         │    REQUEST      │
                         │  (Editorial ID) │
                         └────────┬────────┘
                                  │
                                  ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                        PHASE 1: FETCH PRIMARY                            │
│                        [MUST BE SYNC - CRÍTICO]                          │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │  QueryEditorialClient.findEditorialById() ──┐                       │ │
│  │                                             │ (needs sectionId)     │ │
│  │                                             ▼                       │ │
│  │  QuerySectionClient.findSectionById() ◄────┘                       │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                        OUTPUT: Editorial + Section                       │
└──────────────────────────────────────────────────────────────────────────┘
                                  │
                                  │ (Todas las fases siguientes
                                  │  dependen de Phase 1)
                                  │
          ┌───────────────────────┼───────────────────────┐
          │                       │                       │
          ▼                       ▼                       ▼
┌─────────────────┐   ┌─────────────────────┐   ┌─────────────────┐
│    PHASE 2      │   │      PHASE 3        │   │    PHASE 4      │
│   EMBEDDED      │   │       TAGS          │   │   MEMBERSHIP    │
│   CONTENT       │   │                     │   │     LINKS       │
│                 │   │   [CAN BE ASYNC]    │   │                 │
│ [MIXED SYNC/    │   │                     │   │ [ALREADY ASYNC] │
│  ASYNC]         │   │ ┌─────────────────┐ │   │                 │
│                 │   │ │ Tag 1  ──────┐  │ │   │ Promise created │
│ Inserted News   │   │ │ Tag 2  ──────┤  │ │   │ immediately     │
│ Recommended     │   │ │ Tag 3  ──────┤  │ │   │                 │
│ Opening MM      │   │ │ Tag N  ──────┘  │ │   └─────────────────┘
│ Main MM         │   │ │    ALL PARALLEL │ │
│                 │   │ └─────────────────┘ │          ▼
│ Promises        │   └─────────────────────┘   ┌─────────────────┐
│ collected ─────►│                             │    PHASE 5      │
└─────────────────┘                             │   BODY PHOTOS   │
          │                                     │                 │
          │                                     │ [CAN BE ASYNC]  │
          │                                     │                 │
          │                                     │ ┌─────────────┐ │
          │                                     │ │Photo 1 ───┐ │ │
          │                                     │ │Photo 2 ───┤ │ │
          │                                     │ │Photo N ───┘ │ │
          │                                     │ │ALL PARALLEL │ │
          │                                     │ └─────────────┘ │
          │                                     └─────────────────┘
          │                                              │
          └──────────────────────┬───────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                    PHASE 6-7: PROMISE RESOLUTION                         │
│                    [BLOCKING WAIT - REQUIRED]                            │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │  Utils::settle($multimediaPromises)->wait()    [BLOCKING]          │ │
│  │  $membershipPromise->wait()                    [BLOCKING]          │ │
│  └────────────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                    PHASE 8: AGGREGATION                                  │
│                    [MUST BE SYNC - FINAL]                                │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │  Transformers (local, no external calls)                           │ │
│  │  QueryLegacyClient.findCommentsByEditorialId() [CAN BE ASYNC]     │ │
│  │  QueryJournalistClient.findJournalistByAliasId() [CAN BE ASYNC]   │ │
│  │                                    ▲                               │ │
│  │                                    │ DUPLICADO de Phase 2!         │ │
│  └────────────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## 2. CLASIFICACIÓN DE LLAMADAS

### 2.1 MUST_BE_SYNC (No se pueden paralelizar)

Estas llamadas **DEBEN** ser síncronas porque tienen dependencias de datos.

| # | Servicio | Método | Razón |
|---|----------|--------|-------|
| 1 | QueryEditorialClient | findEditorialById() | **Punto de entrada** - Todo depende de esto |
| 2 | QuerySectionClient | findSectionById() | Necesita `sectionId` del editorial |
| 3 | Promise Resolution | Utils::settle()->wait() | Debe esperar todas las promesas |
| 4 | Final Aggregation | ResponseAggregator.aggregate() | Necesita todos los datos previos |

```php
// Ejemplo de dependencia obligatoria
$editorial = $this->editorialClient->findEditorialById($id);  // PRIMERO
$section = $this->sectionClient->findSectionById(
    $editorial->sectionId()  // ← DEPENDE del editorial
);
```

---

### 2.2 CAN_BE_ASYNC (Actualmente sync, pero parallelizables)

Estas llamadas **PUEDEN** ser asíncronas porque son independientes.

#### A) Loops de Tags (N+1 Problem) ⚠️ CRÍTICO

| Servicio | Método | Ubicación | Impacto |
|----------|--------|-----------|---------|
| QueryTagClient | findTagById() | EditorialOrchestrator.fetchTags() | 2s × N tags |

```php
// ACTUAL (secuencial, N+1)
foreach ($editorial->tags() as $tag) {
    $tags[] = $this->tagClient->findTagById($tag->id());  // 2s cada uno
}
// 5 tags = 10 segundos

// PROPUESTO (paralelo)
$promises = [];
foreach ($editorial->tags() as $tag) {
    $promises[$tag->id()] = $this->tagClient->findTagById($tag->id(), async: true);
}
$tags = Utils::settle($promises)->wait();
// 5 tags = 2 segundos (máximo del más lento)
```

#### B) Loops de Photos (N+1 Problem) ⚠️ CRÍTICO

| Servicio | Método | Ubicación | Impacto |
|----------|--------|-----------|---------|
| QueryMultimediaClient | findPhotoById() | EditorialOrchestrator.retrievePhotosFromBodyTags() | 2s × N photos |

```php
// ACTUAL (secuencial, N+1)
foreach ($bodyTagPictures as $picture) {
    $photos[$id] = $this->multimediaClient->findPhotoById($id);  // 2s cada uno
}

// PROPUESTO (paralelo)
$promises = [];
foreach ($bodyTagPictures as $picture) {
    $promises[$id] = $this->multimediaClient->findPhotoById($id, async: true);
}
$photos = Utils::settle($promises)->wait();
```

#### C) Loops de Journalists (N+1 Problem + DUPLICADO) ⚠️⚠️ MUY CRÍTICO

| Servicio | Método | Ubicación | Impacto |
|----------|--------|-----------|---------|
| QueryJournalistClient | findJournalistByAliasId() | EmbeddedContentFetcher.fetchSignatures() | 2s × M signatures |
| QueryJournalistClient | findJournalistByAliasId() | ResponseAggregator.buildSignatures() | 2s × M **DUPLICADO** |

```php
// ACTUAL (duplicado, secuencial)
// Llamada 1: EmbeddedContentFetcher
foreach ($signatures as $sig) {
    $journalist = $this->journalistClient->findJournalistByAliasId($sig->id());
}

// Llamada 2: ResponseAggregator (¡MISMO DATO!)
foreach ($signatures as $sig) {
    $journalist = $this->journalistClient->findJournalistByAliasId($sig->id());
}

// PROPUESTO (una vez, paralelo, cacheado)
$journalistIds = $this->collectAllJournalistIds($editorial);
$promises = [];
foreach ($journalistIds as $id) {
    $promises[$id] = $this->journalistClient->findJournalistByAliasId($id, async: true);
}
$journalists = Utils::settle($promises)->wait();
// Usar $journalists en ambos lugares
```

#### D) Embedded Editorials (N+1 Problem)

| Servicio | Método | Ubicación | Impacto |
|----------|--------|-----------|---------|
| QueryEditorialClient | findEditorialById() | EmbeddedContentFetcher.fetchInsertedNews() | 2s × N inserted |
| QueryEditorialClient | findEditorialById() | EmbeddedContentFetcher.fetchRecommendedEditorials() | 2s × N recommended |

```php
// ACTUAL (secuencial por cada embedded)
foreach ($insertedNews as $news) {
    $editorial = $this->editorialClient->findEditorialById($news->id());
    $section = $this->sectionClient->findSectionById($editorial->sectionId());
    // ... more calls
}

// PROPUESTO (paralelo batch)
$editorialPromises = [];
foreach ($insertedNews as $news) {
    $editorialPromises[$news->id()] = $this->editorialClient->findEditorialById(
        $news->id(),
        async: true
    );
}
$editorials = Utils::settle($editorialPromises)->wait();
// Luego batch sections...
```

#### E) Comments Count

| Servicio | Método | Ubicación | Impacto |
|----------|--------|-----------|---------|
| QueryLegacyClient | findCommentsByEditorialId() | ResponseAggregator.getCommentsCount() | 2s (único) |

```php
// ACTUAL (sync en Phase 8, tarde)
$comments = $this->legacyClient->findCommentsByEditorialId($id);

// PROPUESTO (async, iniciado temprano con otras llamadas)
$commentsPromise = $this->legacyClient->findCommentsByEditorialId($id, async: true);
// Resolver junto con otras promesas
```

---

### 2.3 ALREADY_ASYNC (Ya paralelizadas) ✅

| Servicio | Método | Ubicación |
|----------|--------|-----------|
| QueryMultimediaClient | findMultimediaById(async: true) | EmbeddedContentFetcher |
| QueryMembershipClient | getMembershipUrl(async: true) | EditorialOrchestrator |

```php
// Ya implementado correctamente
$promises[] = $this->multimediaClient->findMultimediaById($id, async: true);
// ...
Utils::settle($promises)->wait();
```

---

### 2.4 LOCAL (Sin llamadas externas)

| Componente | Método | Descripción |
|------------|--------|-------------|
| DataTransformers | write()/read() | Transformación local de datos |
| BodyDataTransformer | execute() | Procesamiento de body elements |

---

## 3. MATRIZ DE DEPENDENCIAS DETALLADA

```
LEYENDA:
  ● = Depende de (debe ejecutarse después)
  ○ = Independiente (puede ejecutarse en paralelo)
  ◐ = Parcialmente dependiente

                           │ Ph1 │ Ph2 │ Ph3 │ Ph4 │ Ph5 │ Ph6 │ Ph7 │ Ph8 │
───────────────────────────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┤
Phase 1: Primary Fetch     │  -  │  -  │  -  │  -  │  -  │  -  │  -  │  -  │
Phase 2: Embedded Content  │  ●  │  -  │  ○  │  ○  │  ○  │  -  │  -  │  -  │
Phase 3: Tags              │  ●  │  ○  │  -  │  ○  │  ○  │  -  │  -  │  -  │
Phase 4: Membership        │  ●  │  ○  │  ○  │  -  │  ○  │  -  │  -  │  -  │
Phase 5: Body Photos       │  ●  │  ○  │  ○  │  ○  │  -  │  -  │  -  │  -  │
Phase 6: Resolve MM        │  ●  │  ●  │  ○  │  ○  │  ○  │  -  │  ○  │  -  │
Phase 7: Resolve Memb      │  ●  │  ○  │  ○  │  ●  │  ○  │  ○  │  -  │  -  │
Phase 8: Aggregation       │  ●  │  ●  │  ●  │  ●  │  ●  │  ●  │  ●  │  -  │
```

---

## 4. PLAN DE PARALELIZACIÓN

### Nivel 1: Quick Wins (Sin cambios de arquitectura)

| Cambio | Impacto | Esfuerzo |
|--------|---------|----------|
| Tags en paralelo | 10s → 2s | Bajo |
| Photos en paralelo | 6s → 2s | Bajo |
| Comments async (iniciar temprano) | 2s → 0s (paralelo) | Bajo |

### Nivel 2: Mejoras Significativas

| Cambio | Impacto | Esfuerzo |
|--------|---------|----------|
| Journalists deduplicados + paralelo | 12s → 2s | Medio |
| Embedded editorials en paralelo | 8s → 2s | Medio |

### Nivel 3: Refactor Mayor

| Cambio | Impacto | Esfuerzo |
|--------|---------|----------|
| Batch fetching (múltiples IDs en una llamada) | Reduce llamadas HTTP | Alto |
| Caching de journalists | Elimina duplicados | Medio |

---

## 5. FLUJO OPTIMIZADO PROPUESTO

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         PHASE 1: PRIMARY                                │
│                         [SYNC - OBLIGATORIO]                            │
│                                                                         │
│  Editorial ──────────► Section                                          │
│                                                                         │
└─────────────────────────────┬───────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    PHASE 2-5: PARALLEL BATCH                            │
│                    [TODO EN PARALELO]                                   │
│                                                                         │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐  │
│  │ EMBEDDED     │ │    TAGS      │ │  MEMBERSHIP  │ │    PHOTOS    │  │
│  │ CONTENT      │ │   (batch)    │ │   (promise)  │ │   (batch)    │  │
│  │              │ │              │ │              │ │              │  │
│  │ Inserted[N]  │ │ Tag[1..N]    │ │ Links        │ │ Photo[1..P]  │  │
│  │ Recommended  │ │ ALL PARALLEL │ │ async:true   │ │ ALL PARALLEL │  │
│  │ Opening      │ │              │ │              │ │              │  │
│  │ Main MM      │ │              │ │              │ │              │  │
│  └──────┬───────┘ └──────┬───────┘ └──────┬───────┘ └──────┬───────┘  │
│         │                │                │                │          │
│         │                │                │                │          │
│  ┌──────┴────────────────┴────────────────┴────────────────┴───────┐  │
│  │                      COMMENTS (async, iniciado aquí)             │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    PHASE 6: RESOLVE ALL                                 │
│                    [BLOCKING - ÚNICO WAIT]                              │
│                                                                         │
│  Utils::settle([                                                        │
│      ...multimediaPromises,                                             │
│      ...tagPromises,                                                    │
│      ...photoPromises,                                                  │
│      ...journalistPromises,                                             │
│      membershipPromise,                                                 │
│      commentsPromise,                                                   │
│  ])->wait()                                                             │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    PHASE 7: AGGREGATION                                 │
│                    [SYNC - LOCAL ONLY]                                  │
│                                                                         │
│  Transformers (no external calls)                                       │
│  All data already fetched                                               │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 6. ESTIMACIÓN DE MEJORA

### Tiempo Actual (Peor Caso)

| Fase | Operación | Tiempo |
|------|-----------|--------|
| 1 | Editorial + Section | 4s |
| 2 | Embedded (3 insertados × 4s) | 12s |
| 3 | Tags (5 tags × 2s) | 10s |
| 4 | Membership | 0s (async) |
| 5 | Photos (3 × 2s) | 6s |
| 6 | Resolve Multimedia | 2s |
| 7 | Resolve Membership | 2s |
| 8 | Aggregation + Journalists (3 × 2s × 2) | 14s |
| **TOTAL** | | **50s** |

### Tiempo Optimizado (Propuesto)

| Fase | Operación | Tiempo |
|------|-----------|--------|
| 1 | Editorial + Section | 4s |
| 2-5 | TODO EN PARALELO | max(2s, 2s, 2s, 2s) = **2s** |
| 6 | Resolve ALL promises | 0s (ya resueltas) |
| 7 | Aggregation (local only) | 0.1s |
| **TOTAL** | | **~6s** |

### Mejora: **50s → 6s = 88% reducción**

---

## 7. CHECKLIST DE IMPLEMENTACIÓN

### Clientes que necesitan `async` parameter:

- [ ] `QueryTagClient::findTagById(string $id, bool $async = false)`
- [ ] `QueryJournalistClient::findJournalistByAliasId(AliasId $id, bool $async = false)`
- [ ] `QueryMultimediaClient::findPhotoById(string $id, bool $async = false)`
- [ ] `QueryLegacyClient::findCommentsByEditorialId(string $id, bool $async = false)`
- [ ] `QueryEditorialClient::findEditorialById(string $id, bool $async = false)`
- [ ] `QuerySectionClient::findSectionById(string $id, bool $async = false)`

### Servicios a modificar:

- [ ] `EditorialOrchestrator::fetchTags()` → Batch parallel
- [ ] `EditorialOrchestrator::retrievePhotosFromBodyTags()` → Batch parallel
- [ ] `EmbeddedContentFetcher::fetchSignatures()` → Batch parallel
- [ ] `ResponseAggregator::buildSignatures()` → Use cached journalists
- [ ] `ResponseAggregator::getCommentsCount()` → Start early, resolve late

### Nuevos componentes:

- [ ] `ParallelFetcherInterface` - Abstracción para batch operations
- [ ] `BatchResult` DTO - Resultados con fulfilled/rejected
- [ ] `JournalistCache` - Evitar duplicados

---

## 8. CONCLUSIÓN

### Llamadas que DEBEN ser SYNC:
1. `findEditorialById()` - Punto de entrada
2. `findSectionById()` - Depende de editorial
3. `Utils::settle()->wait()` - Punto de sincronización

### Llamadas que PUEDEN ser ASYNC:
1. **Tags** - Todos independientes entre sí
2. **Photos** - Todos independientes entre sí
3. **Journalists** - Todos independientes, DEDUPLICAR
4. **Embedded Editorials** - Todos independientes entre sí
5. **Comments** - Independiente, iniciar temprano
6. **Membership** - Ya es async

### Patrón recomendado:
**Promise Collection + Single Resolution Point**

```php
// Recolectar TODAS las promesas
$allPromises = [
    ...$tagPromises,
    ...$photoPromises,
    ...$journalistPromises,
    ...$embeddedPromises,
    $membershipPromise,
    $commentsPromise,
];

// UN SOLO punto de espera
$results = Utils::settle($allPromises)->wait();

// Procesar resultados localmente (sin más llamadas externas)
$response = $this->aggregator->aggregate($results);
```
