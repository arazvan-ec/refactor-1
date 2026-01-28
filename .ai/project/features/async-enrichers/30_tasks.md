# Tasks: Async Enrichers & Pipeline Steps

**Feature ID**: async-enrichers
**Created**: 2026-01-27

---

## Phase 0: Verificación de Clients (PREREQUISITO)

### Task 0.1: Verificar Soporte Async en QueryTagClient
**Complejidad**: LOW
**Tipo**: INVESTIGACIÓN

```bash
# Buscar en el código del client externo
grep -r "async" vendor/ec/tag-client/
```

**Resultado esperado**:
- Si soporta: método `findTagById(string $id, bool $async = false)`
- Si no soporta: Necesita PR al package externo o wrapper

---

### Task 0.2: Verificar Soporte Async en QueryMultimediaClient
**Complejidad**: LOW
**Tipo**: INVESTIGACIÓN

```bash
grep -r "async\|Async" vendor/ec/multimedia-client/
```

---

### Task 0.3: Verificar Soporte Async en Fetchers
**Complejidad**: LOW
**Tipo**: INVESTIGACIÓN

Revisar:
- `CommentsFetcher` - ¿puede retornar Promise?
- `SignatureFetcher` - ¿puede retornar Promise?

---

## Phase 1: Infraestructura (si es necesaria)

### Task 1.1: Crear BatchResult DTO
**File**: `src/Application/DTO/BatchResult.php`
**Complejidad**: LOW

```php
<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * Result of batch promise resolution.
 */
final readonly class BatchResult
{
    /**
     * @param array<string, mixed> $fulfilled Successfully resolved values
     * @param array<string, \Throwable> $rejected Failed promises with reasons
     */
    public function __construct(
        public array $fulfilled,
        public array $rejected,
    ) {}

    public function hasFailed(): bool
    {
        return !empty($this->rejected);
    }

    public function allSucceeded(): bool
    {
        return empty($this->rejected);
    }
}
```

---

### Task 1.2: Extender PromiseResolverInterface
**File**: `src/Application/Service/Promise/PromiseResolverInterface.php`
**Complejidad**: LOW

Añadir método genérico:

```php
/**
 * Resolve any array of promises in parallel.
 *
 * @param array<string, PromiseInterface|Promise> $promises
 * @return BatchResult
 */
public function resolveAll(array $promises): BatchResult;
```

---

### Task 1.3: Implementar resolveAll en PromiseResolver
**File**: `src/Application/Service/Promise/PromiseResolver.php`
**Complejidad**: MEDIUM

```php
public function resolveAll(array $promises): BatchResult
{
    if (empty($promises)) {
        return new BatchResult([], []);
    }

    $settled = Utils::settle($promises)->wait(true);

    $fulfilled = [];
    $rejected = [];

    foreach ($settled as $key => $result) {
        if ($result['state'] === self::PROMISE_STATE_FULFILLED) {
            $fulfilled[$key] = $result['value'];
        } else {
            $rejected[$key] = $result['reason'] ?? new \RuntimeException('Unknown error');
            $this->logger->warning('Promise rejected', [
                'key' => $key,
                'reason' => $rejected[$key]->getMessage(),
            ]);
        }
    }

    return new BatchResult($fulfilled, $rejected);
}
```

---

## Phase 2: Refactorizar TagsEnricher

### Task 2.1: Actualizar TagsEnricher para Async
**File**: `src/Orchestrator/Enricher/TagsEnricher.php`
**Complejidad**: MEDIUM
**Dependencia**: Task 0.1 (verificar client soporta async)

**Si el client soporta `async: true`**:

```php
public function enrich(EditorialContext $context): void
{
    $tagRefs = $context->editorial->tags()->getArrayCopy();

    if (empty($tagRefs)) {
        $context->withTags([]);
        return;
    }

    // Crear promises para todas las tags
    $promises = [];
    foreach ($tagRefs as $tagRef) {
        $promises[$tagRef->id()] = $this->queryTagClient->findTagById(
            $tagRef->id(),
            async: true  // Parámetro async
        );
    }

    // Resolver en paralelo
    $result = $this->promiseResolver->resolveAll($promises);

    // Loguear rechazados
    foreach ($result->rejected as $tagId => $error) {
        $this->logger->warning('Failed to fetch tag', [
            'tag_id' => $tagId,
            'error' => $error->getMessage(),
        ]);
    }

    $context->withTags(array_values($result->fulfilled));
}
```

**Si el client NO soporta async** (alternativa con wrapper):

```php
// Crear wrapper que use el client síncrono con deferred promises
$promises = [];
foreach ($tagRefs as $tagRef) {
    $promise = new FulfilledPromise(null)->then(function () use ($tagRef) {
        return $this->queryTagClient->findTagById($tagRef->id());
    });
    $promises[$tagRef->id()] = $promise;
}
```

---

### Task 2.2: Actualizar TagsEnricher Test
**File**: `tests/Unit/Orchestrator/Enricher/TagsEnricherTest.php`
**Complejidad**: MEDIUM

```php
#[Test]
public function it_fetches_tags_in_parallel(): void
{
    // Arrange: Create promises
    $tag1Promise = new FulfilledPromise($this->createMock(Tag::class));
    $tag2Promise = new FulfilledPromise($this->createMock(Tag::class));

    $this->queryTagClient
        ->expects(self::exactly(2))
        ->method('findTagById')
        ->willReturnOnConsecutiveCalls($tag1Promise, $tag2Promise);

    $this->promiseResolver
        ->expects(self::once())
        ->method('resolveAll')
        ->willReturn(new BatchResult(['tag1' => $tag1, 'tag2' => $tag2], []));

    // Act
    $context = $this->createContext(['tag-1', 'tag-2']);
    $this->enricher->enrich($context);

    // Assert
    self::assertCount(2, $context->getTags());
}
```

---

## Phase 3: Refactorizar PhotoBodyTagsEnricher

### Task 3.1: Actualizar PhotoBodyTagsEnricher para Async
**File**: `src/Orchestrator/Enricher/PhotoBodyTagsEnricher.php`
**Complejidad**: MEDIUM
**Dependencia**: Task 0.2

```php
public function enrich(EditorialContext $context): void
{
    $photoIds = $this->extractPhotoIds($context->editorial->body());

    if (empty($photoIds)) {
        $context->withPhotoBodyTags([]);
        return;
    }

    // Crear promises para todas las fotos
    $promises = [];
    foreach ($photoIds as $id) {
        $promises[$id] = $this->queryMultimediaClient->findPhotoById($id, async: true);
    }

    // Resolver en paralelo
    $result = $this->promiseResolver->resolveAll($promises);

    // Loguear rechazados
    foreach ($result->rejected as $photoId => $error) {
        $this->logger->error('Failed to fetch photo', [
            'photo_id' => $photoId,
            'error' => $error->getMessage(),
        ]);
    }

    $context->withPhotoBodyTags($result->fulfilled);
}

private function extractPhotoIds(Body $body): array
{
    $ids = [];

    foreach ($body->bodyElementsOf(BodyTagPicture::class) as $picture) {
        $ids[] = $picture->id()->id();
    }

    foreach ($body->bodyElementsOf(BodyTagMembershipCard::class) as $card) {
        $ids[] = $card->bodyTagPictureMembership()->id()->id();
    }

    return array_unique($ids);
}
```

---

### Task 3.2: Actualizar PhotoBodyTagsEnricher Test
**File**: `tests/Unit/Orchestrator/Enricher/PhotoBodyTagsEnricherTest.php`
**Complejidad**: MEDIUM

---

## Phase 4: Refactorizar FetchExternalDataStep

### Task 4.1: Añadir Métodos Async a Fetcher Interfaces
**Files**:
- `src/Orchestrator/Service/CommentsFetcherInterface.php`
- `src/Orchestrator/Service/SignatureFetcherInterface.php`
**Complejidad**: LOW

```php
// CommentsFetcherInterface
public function fetchCommentsCount(string $editorialId): int;
public function fetchCommentsCountAsync(string $editorialId): PromiseInterface;

// SignatureFetcherInterface
public function fetchSignatures(NewsBase $editorial, Section $section): array;
public function fetchSignaturesAsync(NewsBase $editorial, Section $section): PromiseInterface;
```

---

### Task 4.2: Implementar Métodos Async en Fetchers
**Files**:
- `src/Orchestrator/Service/CommentsFetcher.php`
- `src/Orchestrator/Service/SignatureFetcher.php`
**Complejidad**: MEDIUM

```php
// CommentsFetcher
public function fetchCommentsCountAsync(string $editorialId): PromiseInterface
{
    return $this->legacyClient->findCommentsByEditorialIdAsync($editorialId)
        ->then(fn($comments) => count($comments));
}

// SignatureFetcher - usar deferred si no hay async nativo
public function fetchSignaturesAsync(NewsBase $editorial, Section $section): PromiseInterface
{
    $deferred = new Deferred();

    try {
        $result = $this->fetchSignatures($editorial, $section);
        $deferred->resolve($result);
    } catch (\Throwable $e) {
        $deferred->reject($e);
    }

    return $deferred->promise();
}
```

---

### Task 4.3: Actualizar FetchExternalDataStep
**File**: `src/Orchestrator/Pipeline/Step/FetchExternalDataStep.php`
**Complejidad**: MEDIUM

```php
public function process(EditorialPipelineContext $context): StepResult
{
    if (!$context->hasEditorial() || !$context->hasSection()) {
        return StepResult::skip();
    }

    $editorial = $context->getEditorial();
    $section = $context->getSection();

    // Crear promises
    $promises = [
        'comments' => $this->commentsFetcher->fetchCommentsCountAsync(
            $editorial->id()->id()
        ),
        'signatures' => $this->signatureFetcher->fetchSignaturesAsync(
            $editorial,
            $section
        ),
    ];

    // Resolver en paralelo
    $result = $this->promiseResolver->resolveAll($promises);

    // Extraer con defaults
    $commentsCount = $result->fulfilled['comments'] ?? 0;
    $signatures = $result->fulfilled['signatures'] ?? [];

    $context->setPreFetchedData(new PreFetchedDataDTO($commentsCount, $signatures));

    return StepResult::continue();
}
```

---

### Task 4.4: Actualizar Tests
**File**: `tests/Unit/Orchestrator/Pipeline/Step/FetchExternalDataStepTest.php`
**Complejidad**: MEDIUM

---

## Phase 5: Actualizar Dependencias

### Task 5.1: Actualizar Constructores
**Complejidad**: LOW

Los enrichers y steps ahora necesitan `PromiseResolverInterface`:

```php
// TagsEnricher
public function __construct(
    private readonly QueryTagClient $queryTagClient,
    private readonly PromiseResolverInterface $promiseResolver,  // Nuevo
    private readonly LoggerInterface $logger,
) {}
```

---

## Summary

| Phase | Tasks | Complejidad | Dependencias |
|-------|-------|-------------|--------------|
| 0 | 3 | LOW | Ninguna |
| 1 | 3 | LOW-MEDIUM | Phase 0 |
| 2 | 2 | MEDIUM | Phase 1 |
| 3 | 2 | MEDIUM | Phase 1 |
| 4 | 4 | MEDIUM | Phase 1 |
| 5 | 1 | LOW | Phases 2-4 |

**Total**: 15 tasks

---

## Dependency Graph

```
Phase 0: Verificar Clients
    ↓
Phase 1: Infraestructura (BatchResult, resolveAll)
    ↓
    ├── Phase 2: TagsEnricher async
    ├── Phase 3: PhotoBodyTagsEnricher async
    └── Phase 4: FetchExternalDataStep parallel
              ↓
         Phase 5: Wiring
```

---

## Execution Order

1. **[CRITICAL]** Task 0.1-0.3: Verificar si clients soportan async
2. Task 1.1-1.3: Crear infraestructura
3. Task 2.1-2.2: TagsEnricher (paralelo con Phase 3)
4. Task 3.1-3.2: PhotoBodyTagsEnricher (paralelo con Phase 2)
5. Task 4.1-4.4: FetchExternalDataStep
6. Task 5.1: Wiring final
