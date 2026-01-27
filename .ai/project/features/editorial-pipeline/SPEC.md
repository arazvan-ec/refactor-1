# Spec: Editorial Pipeline Pattern

**Feature ID**: editorial-pipeline
**Created**: 2026-01-27
**Status**: DESIGN
**Extends**: refactor-orchestrator-coupling

---

## Problem Statement

Aunque el Content Enricher Chain mejoró el código, `EditorialOrchestrator` aún tiene:

1. **7 dependencias** en el constructor
2. **~50 líneas** de lógica secuencial explícita
3. **Lógica de decisión** (if legacy → return early)
4. **Creación manual** de contexto y DTOs
5. **Orquestación explícita** de cada paso

Cada nuevo requisito (ej: nuevo tipo de early return, nuevo paso de procesamiento) requiere modificar `EditorialOrchestrator`.

---

## Proposed Solution: Editorial Pipeline Pattern

### Concepto

Transformar `EditorialOrchestrator` en un **invocador de pipeline** donde cada paso es un servicio auto-registrado con prioridad y capacidad de:

1. **Continuar** al siguiente paso
2. **Terminar** con respuesta (early return)
3. **Saltar** si no aplica

```
EditorialOrchestrator (10 líneas)
    → EditorialPipelineHandler
        → FetchEditorialStep (priority: 1000) → puede terminar si not found
        → LegacyCheckStep (priority: 900) → termina si es legacy
        → FetchEmbeddedContentStep (priority: 800)
        → EnrichContextStep (priority: 700)
        → ResolvePromisesStep (priority: 600)
        → FetchExternalDataStep (priority: 500)
        → AggregateResponseStep (priority: 100) → termina con respuesta final
```

### Interface Principal

```php
<?php

namespace App\Orchestrator\Pipeline;

/**
 * Result of a pipeline step execution.
 */
final class StepResult
{
    private function __construct(
        private readonly StepResultType $type,
        private readonly ?array $response = null,
    ) {}

    public static function continue(): self
    {
        return new self(StepResultType::CONTINUE);
    }

    public static function skip(): self
    {
        return new self(StepResultType::SKIP);
    }

    public static function terminate(array $response): self
    {
        return new self(StepResultType::TERMINATE, $response);
    }

    public function shouldTerminate(): bool
    {
        return $this->type === StepResultType::TERMINATE;
    }

    public function shouldSkip(): bool
    {
        return $this->type === StepResultType::SKIP;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }
}

enum StepResultType: string
{
    case CONTINUE = 'continue';
    case SKIP = 'skip';
    case TERMINATE = 'terminate';
}
```

```php
<?php

namespace App\Orchestrator\Pipeline;

/**
 * Interface for pipeline steps.
 *
 * Each step processes the context and returns a result indicating
 * whether to continue, skip, or terminate the pipeline.
 */
interface EditorialPipelineStepInterface
{
    /**
     * Process this step of the pipeline.
     *
     * @return StepResult Continue, Skip, or Terminate with response
     */
    public function process(EditorialPipelineContext $context): StepResult;

    /**
     * Get the priority of this step.
     * Higher priority = executed first.
     */
    public function getPriority(): int;

    /**
     * Get a human-readable name for logging/debugging.
     */
    public function getName(): string;
}
```

### Pipeline Context (Mutable State Container)

```php
<?php

namespace App\Orchestrator\Pipeline;

use App\Application\DTO\EmbeddedContentDTO;
use App\Application\DTO\FetchedEditorialDTO;
use App\Application\DTO\PreFetchedDataDTO;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\Tag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mutable context passed through the pipeline.
 *
 * Each step can read from and write to this context.
 * The context accumulates data as it passes through steps.
 */
final class EditorialPipelineContext
{
    // Input (set at creation)
    public readonly Request $request;
    public readonly string $editorialId;

    // Populated by steps
    private ?FetchedEditorialDTO $fetchedEditorial = null;
    private ?Editorial $editorial = null;
    private ?Section $section = null;
    private ?EmbeddedContentDTO $embeddedContent = null;

    // Enriched data
    /** @var array<int, Tag> */
    private array $tags = [];
    /** @var array<string, string> */
    private array $membershipLinks = [];
    /** @var array<string, mixed> */
    private array $photoBodyTags = [];
    /** @var array<string, mixed> */
    private array $resolvedMultimedia = [];
    private ?PreFetchedDataDTO $preFetchedData = null;

    // Extensible custom data
    /** @var array<string, mixed> */
    private array $customData = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->editorialId = (string) $request->get('id');
    }

    // Setters (called by steps)
    public function setFetchedEditorial(FetchedEditorialDTO $dto): void
    {
        $this->fetchedEditorial = $dto;
        $this->editorial = $dto->editorial;
        $this->section = $dto->section;
    }

    public function setEmbeddedContent(EmbeddedContentDTO $content): void
    {
        $this->embeddedContent = $content;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function setMembershipLinks(array $links): void
    {
        $this->membershipLinks = $links;
    }

    public function setPhotoBodyTags(array $photos): void
    {
        $this->photoBodyTags = $photos;
    }

    public function setResolvedMultimedia(array $multimedia): void
    {
        $this->resolvedMultimedia = $multimedia;
    }

    public function setPreFetchedData(PreFetchedDataDTO $data): void
    {
        $this->preFetchedData = $data;
    }

    public function setCustomData(string $key, mixed $value): void
    {
        $this->customData[$key] = $value;
    }

    // Getters
    public function getFetchedEditorial(): ?FetchedEditorialDTO
    {
        return $this->fetchedEditorial;
    }

    public function getEditorial(): ?Editorial
    {
        return $this->editorial;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function getEmbeddedContent(): ?EmbeddedContentDTO
    {
        return $this->embeddedContent;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getMembershipLinks(): array
    {
        return $this->membershipLinks;
    }

    public function getPhotoBodyTags(): array
    {
        return $this->photoBodyTags;
    }

    public function getResolvedMultimedia(): array
    {
        return $this->resolvedMultimedia;
    }

    public function getPreFetchedData(): ?PreFetchedDataDTO
    {
        return $this->preFetchedData;
    }

    public function getCustomData(string $key, mixed $default = null): mixed
    {
        return $this->customData[$key] ?? $default;
    }

    // Convenience checks
    public function hasEditorial(): bool
    {
        return $this->editorial !== null;
    }

    public function hasEmbeddedContent(): bool
    {
        return $this->embeddedContent !== null;
    }
}
```

### Pipeline Handler

```php
<?php

namespace App\Orchestrator\Pipeline;

use Psr\Log\LoggerInterface;

/**
 * Executes pipeline steps in priority order.
 *
 * Steps are registered via EditorialPipelineCompiler.
 */
final class EditorialPipelineHandler
{
    /** @var array<int, EditorialPipelineStepInterface> */
    private array $steps = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function addStep(EditorialPipelineStepInterface $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * Execute the pipeline and return the final response.
     *
     * @return array<string, mixed> The API response
     *
     * @throws \RuntimeException If pipeline completes without a response
     */
    public function execute(EditorialPipelineContext $context): array
    {
        // Sort by priority (higher first)
        usort($this->steps, fn($a, $b) => $b->getPriority() <=> $a->getPriority());

        foreach ($this->steps as $step) {
            $this->logger->debug('Executing pipeline step', [
                'step' => $step->getName(),
                'priority' => $step->getPriority(),
            ]);

            try {
                $result = $step->process($context);

                if ($result->shouldTerminate()) {
                    $this->logger->debug('Pipeline terminated by step', [
                        'step' => $step->getName(),
                    ]);
                    return $result->getResponse();
                }

                if ($result->shouldSkip()) {
                    $this->logger->debug('Step skipped', [
                        'step' => $step->getName(),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Pipeline step failed', [
                    'step' => $step->getName(),
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        throw new \RuntimeException(
            'Pipeline completed without producing a response. ' .
            'Ensure a terminal step (like AggregateResponseStep) is registered.'
        );
    }

    public function count(): int
    {
        return count($this->steps);
    }
}
```

### Example Steps

#### 1. FetchEditorialStep (Priority: 1000)

```php
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 1000])]
final class FetchEditorialStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly EditorialFetcherInterface $fetcher,
    ) {}

    public function process(EditorialPipelineContext $context): StepResult
    {
        $fetched = $this->fetcher->fetch($context->editorialId);
        $context->setFetchedEditorial($fetched);

        return StepResult::continue();
    }

    public function getPriority(): int { return 1000; }
    public function getName(): string { return 'FetchEditorial'; }
}
```

#### 2. LegacyCheckStep (Priority: 900) - Early Termination

```php
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 900])]
final class LegacyCheckStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly EditorialFetcherInterface $fetcher,
    ) {}

    public function process(EditorialPipelineContext $context): StepResult
    {
        if (!$context->hasEditorial()) {
            return StepResult::skip();
        }

        if ($this->fetcher->shouldUseLegacy($context->getEditorial())) {
            // TERMINATE: Return legacy response immediately
            $response = $this->fetcher->fetchLegacy($context->editorialId);
            return StepResult::terminate($response);
        }

        return StepResult::continue();
    }

    public function getPriority(): int { return 900; }
    public function getName(): string { return 'LegacyCheck'; }
}
```

#### 3. AggregateResponseStep (Priority: 100) - Final Step

```php
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 100])]
final class AggregateResponseStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly ResponseAggregatorInterface $aggregator,
    ) {}

    public function process(EditorialPipelineContext $context): StepResult
    {
        $response = $this->aggregator->aggregate(
            $context->getFetchedEditorial(),
            $context->getEmbeddedContent(),
            $context->getTags(),
            $context->getResolvedMultimedia(),
            $context->getMembershipLinks(),
            $context->getPhotoBodyTags(),
            $context->getPreFetchedData(),
        );

        // TERMINATE: This is the final step
        return StepResult::terminate($response);
    }

    public function getPriority(): int { return 100; }
    public function getName(): string { return 'AggregateResponse'; }
}
```

### Refactored EditorialOrchestrator (~15 lines)

```php
<?php

declare(strict_types=1);

namespace App\Orchestrator\Chain;

use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineHandler;
use Symfony\Component\HttpFoundation\Request;

/**
 * Minimal orchestrator that delegates to the pipeline.
 *
 * All logic is in pipeline steps - this class just invokes the pipeline.
 * To add new behavior, create a new step implementing EditorialPipelineStepInterface.
 */
class EditorialOrchestrator implements EditorialOrchestratorInterface
{
    public function __construct(
        private readonly EditorialPipelineHandler $pipeline,
    ) {}

    public function execute(Request $request): array
    {
        $context = new EditorialPipelineContext($request);
        return $this->pipeline->execute($context);
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }
}
```

---

## Complete Step List

| Step | Priority | Description | Result |
|------|----------|-------------|--------|
| `FetchEditorialStep` | 1000 | Fetch editorial + section | Continue |
| `LegacyCheckStep` | 900 | Check if legacy | **Terminate** or Continue |
| `FetchEmbeddedContentStep` | 800 | Fetch inserted/recommended | Continue |
| `EnrichTagsStep` | 700 | Fetch tags | Continue |
| `EnrichMembershipLinksStep` | 690 | Fetch membership links | Continue |
| `EnrichPhotoBodyTagsStep` | 680 | Fetch body tag photos | Continue |
| `ResolveMultimediaStep` | 600 | Resolve multimedia promises | Continue |
| `FetchCommentsStep` | 500 | Fetch comment count | Continue |
| `FetchSignaturesStep` | 490 | Fetch journalist signatures | Continue |
| `AggregateResponseStep` | 100 | Build final response | **Terminate** |

---

## Benefits

| Aspect | Before (Enricher) | After (Pipeline) |
|--------|-------------------|------------------|
| EditorialOrchestrator lines | 148 | ~15 |
| Constructor dependencies | 7 | 1 |
| Logic in orchestrator | Explicit steps | None |
| Adding new step | May need orchestrator change | Just create class |
| Early termination | If statement | StepResult::terminate() |
| Step ordering | Implicit | Explicit priority |
| Debugging | Spread across file | Clear step names in logs |

---

## Migration Path

1. **Create Pipeline infrastructure**
   - `EditorialPipelineStepInterface`
   - `StepResult` enum/class
   - `EditorialPipelineContext`
   - `EditorialPipelineHandler`
   - `EditorialPipelineCompiler`

2. **Extract steps from current orchestrator**
   - Move each responsibility to its own step
   - Migrate enrichers to pipeline steps (or keep both patterns)

3. **Refactor EditorialOrchestrator**
   - Remove all dependencies except pipeline
   - Reduce to ~15 lines

4. **Update tests**
   - Test each step independently
   - Integration test for full pipeline

---

## Compatibility with Enrichers

The existing `ContentEnricherChainHandler` can become a single pipeline step:

```php
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 700])]
final class EnrichContentStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly ContentEnricherChainHandler $enricherChain,
    ) {}

    public function process(EditorialPipelineContext $context): StepResult
    {
        // Convert pipeline context to enricher context
        $enricherContext = new EditorialContext(
            $context->getEditorial(),
            $context->getSection(),
            $context->getEmbeddedContent(),
        );

        $this->enricherChain->enrichAll($enricherContext);

        // Copy enriched data back to pipeline context
        $context->setTags($enricherContext->getTags());
        $context->setMembershipLinks($enricherContext->getMembershipLinks());
        $context->setPhotoBodyTags($enricherContext->getPhotoBodyTags());

        return StepResult::continue();
    }
}
```

---

## Questions for Review

1. **¿Mantener ambos patrones?** (Pipeline + Enricher) o **unificar todo en Pipeline?**
   - Mantener ambos: más flexible, enrichers son más granulares
   - Unificar: más simple, un solo patrón

2. **¿Async steps?** ¿Algunos pasos podrían ejecutarse en paralelo?
   - FetchCommentsStep y FetchSignaturesStep son independientes

3. **¿Error handling por step?** ¿Fail-safe o fail-fast?
   - Actual: fail-fast (excepción detiene pipeline)
   - Alternativa: catch por step, loguear, continuar

---

## Implementation Tasks

1. Create `StepResult` and `StepResultType`
2. Create `EditorialPipelineStepInterface`
3. Create `EditorialPipelineContext`
4. Create `EditorialPipelineHandler`
5. Create `EditorialPipelineCompiler`
6. Create `FetchEditorialStep`
7. Create `LegacyCheckStep`
8. Create `FetchEmbeddedContentStep`
9. Create `EnrichContentStep` (wraps existing enrichers)
10. Create `ResolveMultimediaStep`
11. Create `FetchCommentsStep`
12. Create `FetchSignaturesStep`
13. Create `AggregateResponseStep`
14. Refactor `EditorialOrchestrator`
15. Update tests
16. Register compiler pass

---

## Success Criteria

1. `EditorialOrchestrator` reduced to ~15 lines
2. Single dependency in constructor
3. Adding new step = just create class + tag
4. Early termination via `StepResult::terminate()`
5. All tests pass
6. No changes to API response format
