# Decisions: Refactor EditorialOrchestrator Coupling

**Feature ID**: refactor-orchestrator-coupling
**Created**: 2026-01-27

---

## Decision 1: Content Enricher Chain Pattern

**Date**: 2026-01-27
**Status**: APPROVED
**Decided by**: Planner

### Context

`EditorialOrchestrator` tiene 4 HTTP clients directos que violan OCP (Open/Closed Principle). Se evaluaron varias alternativas:

### Alternatives Evaluated

| Alternative | Pros | Cons |
|-------------|------|------|
| **A. Individual Fetchers** | Simple, ya existe patrón | Aún requiere modificar constructor |
| **B. Content Enricher Chain** | Auto-registro, extensible, solo añadir clase | Nuevo patrón a aprender |
| **C. Event/Listener** | Muy desacoplado | Flujo menos claro, debugging difícil |
| **D. Pipeline** | Funcional, composable | Overkill para este caso |

### Decision

**Opción B: Content Enricher Chain Pattern**

### Rationale

1. **Escalabilidad**: Añadir nuevo enricher = crear clase + tag, nada más
2. **Consistencia**: Similar a patrón existente de DataTransformers
3. **Testabilidad**: Cada enricher testeable independientemente
4. **Claridad**: El flujo sigue siendo explícito (a diferencia de events)
5. **Symfony Idiomático**: Usa tagged services + compiler pass

### Consequences

- Nuevo concepto a documentar (ContentEnricher)
- Todos los devs deben entender el patrón
- Debugging: revisar qué enrichers están registrados

---

## Decision 2: EditorialContext como Mutable DTO

**Date**: 2026-01-27
**Status**: APPROVED
**Decided by**: Planner

### Context

Los enrichers necesitan un lugar donde depositar sus datos. Opciones:

### Alternatives

| Alternative | Pros | Cons |
|-------------|------|------|
| **A. Inmutable + return** | Funcional puro | Cada enricher devuelve nuevo objeto |
| **B. Mutable DTO** | Simple, eficiente | Estado mutable |
| **C. Builder pattern** | Fluent API | Más complejidad |

### Decision

**Opción B: Mutable DTO con campos específicos**

```php
final class EditorialContext
{
    // Readonly input
    public readonly Editorial $editorial;

    // Mutable enriched data
    private array $tags = [];

    public function withTags(array $tags): void { ... }
    public function getTags(): array { ... }
}
```

### Rationale

1. Los enrichers se ejecutan secuencialmente (no paralelo)
2. El contexto solo vive durante una request
3. Simplicidad sobre pureza funcional en este caso
4. `withX()` methods mantienen cierto control sobre mutations

### Consequences

- El objeto no es thread-safe (no aplica en PHP)
- Tests deben verificar estado final, no intermedio

---

## Decision 3: Prioridad de Enrichers

**Date**: 2026-01-27
**Status**: APPROVED
**Decided by**: Planner

### Context

Algunos enrichers podrían depender de datos de otros. ¿Cómo ordenarlos?

### Decision

**Sistema de prioridad numérica (mayor = primero)**

```php
#[AutoconfigureTag('app.content_enricher', ['priority' => 100])]
```

| Enricher | Priority | Reason |
|----------|----------|--------|
| TagsEnricher | 100 | Sin dependencias |
| MembershipLinksEnricher | 90 | Sin dependencias |
| PhotoBodyTagsEnricher | 80 | Sin dependencias |
| (Future) | 70-0 | May depend on above |

### Rationale

1. Patrón estándar de Symfony (EventSubscriber usa lo mismo)
2. Flexible para futuros enrichers
3. Fácil de entender y mantener

### Consequences

- Devs deben elegir prioridad apropiada
- Documentar dependencias entre enrichers si existen

---

## Decision 4: Error Handling en Enrichers

**Date**: 2026-01-27
**Status**: APPROVED
**Decided by**: Planner

### Context

¿Qué pasa si un enricher falla? ¿Falla todo el request?

### Decision

**Fail-safe con logging**: ChainHandler captura excepciones y loguea, pero continúa

```php
foreach ($this->enrichers as $enricher) {
    try {
        $enricher->enrich($context);
    } catch (\Throwable $e) {
        $this->logger->error('Enricher failed: ' . $e->getMessage());
        // Continue with next enricher
    }
}
```

### Rationale

1. Consistente con comportamiento actual (tags fallidos se loguean, no rompen)
2. Graceful degradation (API devuelve lo que pueda)
3. Mejor UX que error 500 por tag no encontrado

### Consequences

- Response puede estar incompleto si enricher falla
- Importante monitorear logs de errores
- Considerar alertas si un enricher falla consistentemente

---

## Decision 5: No mover CommentsFetcher/SignatureFetcher a Enrichers (por ahora)

**Date**: 2026-01-27
**Status**: DEFERRED
**Decided by**: Planner

### Context

`CommentsFetcher` y `SignatureFetcher` ya están abstraídos como interfaces. ¿Deberían ser enrichers también?

### Decision

**Mantener como están (por ahora)**

### Rationale

1. Ya funcionan bien con interfaces
2. Se pasan a `PreFetchedDataDTO` (diferente de `EditorialContext`)
3. Cambiarlos añade scope al refactor
4. Podemos migrar después si tiene sentido

### Consequences

- Dos patrones coexisten (Fetchers + Enrichers)
- Posible inconsistencia
- Documentar la diferencia

### Future Consideration

En un segundo refactor, podríamos:
- Unificar todo bajo Enrichers
- O mantener separation of concerns (fetchers para datos críticos, enrichers para opcionales)

---

## Pending Decisions

### P1: ¿Soporte para Enrichers Async?

**Status**: NEEDS DISCUSSION

Actualmente el plan es síncrono. ¿Deberíamos soportar:

```php
interface AsyncContentEnricherInterface extends ContentEnricherInterface
{
    public function enrichAsync(EditorialContext $context): Promise;
}
```

**Pros**: Mejor performance, parallelización
**Cons**: Más complejidad, el patrón actual ya usa promises en otro lugar

**Decision deferred until**: Después del refactor inicial

---

## Change Log

| Date | Decision | Changed By |
|------|----------|------------|
| 2026-01-27 | Initial decisions | Planner |
