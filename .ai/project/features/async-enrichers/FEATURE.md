# Feature: Async Enrichers & Pipeline Steps

**Feature ID**: async-enrichers
**Created**: 2026-01-27
**Status**: PLANNING
**Priority**: HIGH
**Extends**: editorial-pipeline

---

## Problem Statement

Actualmente hay **llamadas HTTP síncronas secuenciales** que degradan el rendimiento:

| Componente | Problema | Impacto |
|------------|----------|---------|
| `TagsEnricher` | Loop síncrono N tags | N × latencia |
| `PhotoBodyTagsEnricher` | Loop síncrono P fotos | P × latencia |
| `FetchExternalDataStep` | Comments + Signatures secuencial | 2 × latencia |

**Ejemplo real**:
```
Editorial con 5 tags + 3 fotos:
Tags: 5 × 200ms = 1000ms (secuencial)
Fotos: 3 × 200ms = 600ms (secuencial)
External: 2 × 200ms = 400ms (secuencial)
TOTAL: 2000ms

Con async paralelo:
Tags: max(200ms) = 200ms (paralelo)
Fotos: max(200ms) = 200ms (paralelo)
External: max(200ms) = 200ms (paralelo)
TOTAL: 600ms (70% mejora)
```

---

## Solution: Extend Existing Promise Pattern

El proyecto YA usa `GuzzleHttp\Promise\Utils::settle()` para multimedia. Solo hay que **extender el patrón** a tags, fotos y datos externos.

### Patrón Existente (Multimedia)

```php
// Ya funciona así en EmbeddedContentFetcher
$promises = [];
foreach ($multimedias as $m) {
    $promises[] = $client->findMultimediaById($m->id(), async: true);
}
// Todas se ejecutan en paralelo
$results = Utils::settle($promises)->wait(true);
```

### Patrón a Aplicar

```php
// TagsEnricher con async
$promises = [];
foreach ($editorial->tags() as $tag) {
    $promises[$tag->id()] = $this->queryTagClient->findTagById($tag->id(), async: true);
}
$settled = Utils::settle($promises)->wait(true);
$tags = $this->extractFulfilled($settled);
```

---

## Implementation Plan

### Phase 1: Verificar Soporte Async en Clients

Verificar que los HTTP clients soporten el parámetro `async: true`:

| Client | Método | ¿Soporta async? |
|--------|--------|-----------------|
| `QueryTagClient` | `findTagById()` | Verificar |
| `QueryMultimediaClient` | `findPhotoById()` | Verificar |
| `QueryLegacyClient` | `findCommentsByEditorialId()` | Verificar |
| `QueryJournalistClient` | `findJournalistByAliasId()` | Verificar |

### Phase 2: Crear AsyncPromiseResolver

Extender `PromiseResolverInterface` con métodos genéricos:

```php
interface PromiseResolverInterface
{
    // Existente
    public function resolveMultimedia(array $promises): array;

    // Nuevo: resolver cualquier tipo de promises
    public function resolveAll(array $promises): BatchResult;
}
```

### Phase 3: Refactorizar Enrichers

#### 3.1 TagsEnricher → AsyncTagsEnricher

```php
#[AutoconfigureTag('app.content_enricher', ['priority' => 100])]
final class TagsEnricher implements ContentEnricherInterface
{
    public function enrich(EditorialContext $context): void
    {
        $promises = [];
        foreach ($context->editorial->tags()->getArrayCopy() as $tag) {
            $promises[$tag->id()] = $this->queryTagClient->findTagById(
                $tag->id(),
                async: true
            );
        }

        $settled = Utils::settle($promises)->wait(true);
        $tags = $this->extractFulfilledTags($settled);

        $context->withTags($tags);
    }
}
```

#### 3.2 PhotoBodyTagsEnricher → Async

```php
public function enrich(EditorialContext $context): void
{
    $photoIds = $this->extractPhotoIdsFromBody($context->editorial->body());

    $promises = [];
    foreach ($photoIds as $id) {
        $promises[$id] = $this->queryMultimediaClient->findPhotoById($id, async: true);
    }

    $settled = Utils::settle($promises)->wait(true);
    $photos = $this->extractFulfilledPhotos($settled);

    $context->withPhotoBodyTags($photos);
}
```

### Phase 4: Refactorizar FetchExternalDataStep

Paralelizar comments y signatures:

```php
public function process(EditorialPipelineContext $context): StepResult
{
    $editorial = $context->getEditorial();
    $section = $context->getSection();

    // Crear promises para ambas llamadas
    $commentsPromise = $this->commentsFetcher->fetchCommentsCountAsync(
        $editorial->id()->id()
    );
    $signaturesPromise = $this->signatureFetcher->fetchSignaturesAsync(
        $editorial,
        $section
    );

    // Resolver en paralelo
    $settled = Utils::settle([
        'comments' => $commentsPromise,
        'signatures' => $signaturesPromise,
    ])->wait(true);

    // Extraer resultados
    $commentsCount = $settled['comments']['state'] === 'fulfilled'
        ? $settled['comments']['value']
        : 0;
    $signatures = $settled['signatures']['state'] === 'fulfilled'
        ? $settled['signatures']['value']
        : [];

    $context->setPreFetchedData(new PreFetchedDataDTO($commentsCount, $signatures));

    return StepResult::continue();
}
```

---

## Files to Create/Modify

### New Files
- `src/Application/DTO/BatchResult.php` - DTO para resultados de batch

### Modified Files
- `src/Orchestrator/Enricher/TagsEnricher.php` - Usar promises
- `src/Orchestrator/Enricher/PhotoBodyTagsEnricher.php` - Usar promises
- `src/Orchestrator/Pipeline/Step/FetchExternalDataStep.php` - Paralelizar
- `src/Orchestrator/Service/CommentsFetcherInterface.php` - Añadir async
- `src/Orchestrator/Service/SignatureFetcherInterface.php` - Añadir async

### Tests
- `tests/Unit/Orchestrator/Enricher/TagsEnricherTest.php` - Test async
- `tests/Unit/Orchestrator/Enricher/PhotoBodyTagsEnricherTest.php` - Test async
- `tests/Unit/Orchestrator/Pipeline/Step/FetchExternalDataStepTest.php` - Test parallel

---

## Risk Assessment

### Trust Level: MEDIUM CONTROL

**Razón**:
- Patrón ya probado en el proyecto (multimedia)
- Cambios internos, no afecta API
- Requiere verificar soporte async en clients externos

**Riesgos**:
1. Clients externos podrían no soportar `async: true`
   - **Mitigación**: Verificar antes de implementar, fallback a sync
2. Error handling más complejo con promises
   - **Mitigación**: Usar patrón existente de `extractFulfilled`

---

## Success Criteria

1. TagsEnricher usa promises paralelas
2. PhotoBodyTagsEnricher usa promises paralelas
3. FetchExternalDataStep ejecuta comments + signatures en paralelo
4. Tests unitarios pasan
5. No hay regresiones en API response
6. Mejora medible en tiempo de respuesta

---

## Dependencies

- Los HTTP clients deben soportar `async: true`
- Si no lo soportan, hay que añadir el soporte primero

---

## Questions to Resolve

1. ¿`QueryTagClient::findTagById()` soporta `async: true`?
2. ¿`QueryMultimediaClient::findPhotoById()` soporta `async: true`?
3. ¿Crear métodos `*Async()` separados o usar parámetro `async: true`?

---

## References

- `.claude/features/snaapi-improvements-v2/02_specifications.md` - Spec de paralelización
- `src/Application/Service/Promise/PromiseResolver.php` - Patrón existente
