# Feature: Refactor EditorialOrchestrator Coupling

**Feature ID**: refactor-orchestrator-coupling
**Created**: 2026-01-27
**Status**: PLANNING
**Priority**: HIGH

---

## Problem Statement

`EditorialOrchestrator` tiene acoplamiento directo con 4 HTTP clients:
- `QueryTagClient`
- `QueryMembershipClient`
- `QueryMultimediaClient`
- `UriFactoryInterface`

Cada vez que se necesita añadir un nuevo tipo de datos (ej: related articles, author details), hay que:
1. Añadir una nueva dependencia al constructor
2. Crear un método privado para la llamada HTTP
3. Modificar `execute()` para llamar ese método
4. Pasar los datos al `ResponseAggregator`

**Esto viola**:
- **OCP** (Open/Closed Principle): Hay que modificar `EditorialOrchestrator` para extender
- **SRP** (Single Responsibility): Orchestrator hace demasiadas cosas
- **DIP** (Dependency Inversion): Depende de implementaciones concretas

---

## Proposed Solution: Content Enricher Chain Pattern

### Concepto

Crear un **patrón de enriquecedores** donde cada tipo de datos adicional es un servicio independiente que se auto-registra:

```
EditorialOrchestrator
    → ContentEnricherChainHandler
        → TagsEnricher (auto-registrado)
        → MembershipLinksEnricher (auto-registrado)
        → PhotoBodyTagsEnricher (auto-registrado)
        → [FuturoEnricher] (solo añadir clase)
```

### Cómo funciona

1. **Crear interfaz `ContentEnricherInterface`**:
   ```php
   interface ContentEnricherInterface
   {
       public function enrich(EditorialContext $context): void;
       public function supports(Editorial $editorial): bool;
       public function getPriority(): int;
   }
   ```

2. **EditorialContext como Value Object**:
   ```php
   class EditorialContext
   {
       // Datos de entrada (readonly)
       public readonly Editorial $editorial;
       public readonly Section $section;

       // Datos enriquecidos (mutable durante enrichment)
       private array $tags = [];
       private array $membershipLinks = [];
       private array $photoBodyTags = [];
       private array $customData = [];

       // Métodos para añadir/obtener datos
   }
   ```

3. **Compiler Pass para auto-registro**:
   ```php
   // Tag: app.content_enricher
   // ContentEnricherCompiler registra todos automáticamente
   ```

4. **ContentEnricherChainHandler ejecuta todos**:
   ```php
   class ContentEnricherChainHandler
   {
       /** @param iterable<ContentEnricherInterface> $enrichers */
       public function __construct(private iterable $enrichers) {}

       public function enrichAll(EditorialContext $context): void
       {
           foreach ($this->enrichers as $enricher) {
               if ($enricher->supports($context->editorial)) {
                   $enricher->enrich($context);
               }
           }
       }
   }
   ```

---

## Benefits

| Antes | Después |
|-------|---------|
| Modificar `EditorialOrchestrator` para cada nuevo dato | Solo crear nueva clase con tag |
| 11 dependencias en constructor | 5-6 dependencias (core services) |
| 278 líneas | ~100 líneas |
| Tests requieren 4 HTTP mocks | Tests con 0 HTTP mocks |
| Difícil de extender | Añadir clase = listo |

### Escalabilidad

Para añadir un nuevo tipo de datos (ej: `RelatedArticlesEnricher`):

```php
// 1. Crear la clase (ÚNICO PASO NECESARIO)
#[AutoconfigureTag('app.content_enricher')]
class RelatedArticlesEnricher implements ContentEnricherInterface
{
    public function __construct(
        private QueryRelatedClient $client,
        private LoggerInterface $logger,
    ) {}

    public function enrich(EditorialContext $context): void
    {
        $related = $this->client->findRelated($context->editorial->id());
        $context->addCustomData('relatedArticles', $related);
    }

    public function supports(Editorial $editorial): bool
    {
        return true; // o lógica específica
    }

    public function getPriority(): int
    {
        return 50;
    }
}
```

**No hay que tocar `EditorialOrchestrator`**. El Compiler Pass lo registra automáticamente.

---

## Risk Assessment

### Trust Level: MEDIUM CONTROL

**Razón**:
- Patrón ya probado en el proyecto (DataTransformers, Orchestrators)
- No cambia contratos externos (API response igual)
- Refactor interno, no afecta clientes

**Supervisión requerida**:
- Review de arquitectura antes de implementar
- Tests de arquitectura para validar separación
- Tests de regresión completos

---

## Out of Scope

- Cambios en `ResponseAggregator` (solo recibe `EditorialContext`)
- Cambios en formato de API response
- Nuevos endpoints

---

## Success Criteria

1. `EditorialOrchestrator` sin HTTP clients directos
2. Añadir nuevo enricher = solo crear clase + tag
3. Tests de arquitectura pasan
4. Mutation testing >= 79%
5. API response idéntico (test de regresión)

---

## References

- [Chain of Responsibility Pattern](https://refactoring.guru/design-patterns/chain-of-responsibility)
- [Symfony Tagged Services](https://symfony.com/doc/current/service_container/tags.html)
- Patrón existente: `BodyDataTransformerCompiler`
