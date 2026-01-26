# Principles Specification

**Project**: SNAAPI
**Date**: 2026-01-26

---

## 1. Clean Code Principles

### Applied in SNAAPI

| Principle | Application | Example |
|-----------|-------------|---------|
| **Meaningful Names** | Classes reveal intent | `BodyTagPictureDataTransformer` (not `PicTransf`) |
| **Small Functions** | Methods do one thing | `canTransform()`, `write()`, `read()` |
| **No Comments for Bad Code** | Self-documenting code | Method names explain behavior |
| **DRY** | Shared traits | `MultimediaTrait`, `UrlGeneratorTrait` |
| **Single Level of Abstraction** | Orchestrators delegate | Controller → Orchestrator → Fetcher → Client |

### Specification

```php
// SPEC-CC-001: Method Length
// Methods SHOULD NOT exceed 20 lines
// If exceeded, extract to helper methods

// SPEC-CC-002: Naming Convention
// Classes: PascalCase, descriptive (EditorialOrchestrator)
// Methods: camelCase, verb-first (fetchEditorial, transformBody)
// Variables: camelCase, noun (editorialData, transformedBody)

// SPEC-CC-003: Function Arguments
// Maximum 3 positional arguments
// Use DTOs or named arguments for more

// GOOD
public function transform(Editorial $editorial, TransformContext $context): array;

// AVOID
public function transform($editorial, $section, $tags, $multimedia, $options): array;

// SPEC-CC-004: Return Types
// Always declare return types
// Avoid mixed when possible
public function read(): array;  // Explicit
```

### Violations to Fix

| Location | Issue | Recommendation |
|----------|-------|----------------|
| `EditorialOrchestrator` | 537 lines | Split into smaller collaborators |
| Multiple files | `array<string, mixed>` | Create typed DTOs |

---

## 2. SOLID Principles

### S - Single Responsibility

**Specification**:
```
Each class has ONE reason to change:
- Controller: HTTP handling only
- Orchestrator: Service coordination only
- DataTransformer: Data transformation only
- Client: HTTP communication only
```

**Current Status**: ✅ Mostly compliant

| Component | Responsibility | Status |
|-----------|---------------|--------|
| Controller | HTTP entry point | ✅ Thin |
| Orchestrator | Coordinate services | ⚠️ EditorialOrchestrator too large |
| DataTransformer | Transform data | ✅ Focused |
| Fetcher | Fetch specific data | ✅ Focused |

### O - Open/Closed

**Specification**:
```
Extend behavior via interfaces and tags, not modification:
- Add new DataTransformer: Create class + add tag
- Add new Orchestrator: Create class + add tag
- Add new BodyElement: Create transformer + register
```

**Implementation**: Compiler Passes

```yaml
# services.yaml
services:
    _instanceof:
        App\Application\DataTransformer\BodyElementDataTransformerInterface:
            tags: ['app.data_transformer']
```

```php
// BodyDataTransformerCompiler.php
class BodyDataTransformerCompiler implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $handler = $container->findDefinition(BodyElementDataTransformerHandler::class);

        foreach ($container->findTaggedServiceIds('app.data_transformer') as $id => $tags) {
            $handler->addMethodCall('addDataTransformer', [new Reference($id)]);
        }
    }
}
```

**Status**: ✅ COMPLIANT

### L - Liskov Substitution

**Specification**:
```
All implementations of an interface MUST be substitutable:
- All DataTransformers implement same interface
- All Orchestrators implement same interface
- Return types are consistent
```

**Interface Contract**:
```php
interface BodyElementDataTransformerInterface
{
    public function canTransform(string $type): bool;
    public function write(BodyElement $element, array $data = []): self;
    public function read(): array;
}
```

**Status**: ✅ COMPLIANT

### I - Interface Segregation

**Specification**:
```
Small, focused interfaces:
- BodyElementDataTransformerInterface: for body elements
- MediaDataTransformerInterface: for multimedia
- EditorialOrchestratorInterface: for orchestration
```

**Status**: ✅ COMPLIANT

### D - Dependency Inversion

**Specification**:
```
Depend on abstractions, not concretions:
- Inject interfaces, not classes
- Use constructor injection exclusively
- Configure via services.yaml
```

**Current Issues**:

```php
// VIOLATION: Thumbor (Infrastructure) injected into DataTransformer (Application)
class JournalistsDataTransformer
{
    public function __construct(
        private readonly Thumbor $thumbor  // Concrete class, not interface
    ) {}
}
```

**Recommendation**:
```php
// SOLUTION 1: Create interface
interface ImageUrlBuilderInterface
{
    public function buildJournalistUrl(string $photo): string;
}

// SOLUTION 2: Move URL building to ResponseAggregator (Infrastructure concern)
```

---

## 3. REST API Principles

### URL Structure

**Specification**:
```
- Use plural nouns: /editorials (not /editorial)
- Version in URL: /v1/editorials
- Resource identifiers in path: /v1/editorials/{id}
- Query params for filtering: /v1/editorials?section=sports
```

**Current Implementation**:
```php
#[Route('/v1/editorials/{id}', name: 'editorial_get', methods: ['GET'])]
```

**Status**: ✅ COMPLIANT

### HTTP Methods

| Method | Usage | Example |
|--------|-------|---------|
| GET | Retrieve resource | `GET /v1/editorials/{id}` |
| POST | Create resource | Not applicable (read-only API) |
| PUT | Update resource | Not applicable |
| DELETE | Remove resource | Not applicable |

**Note**: SNAAPI is read-only, only GET methods needed.

### Status Codes

**Specification**:
```
200 OK - Successful GET
400 Bad Request - Invalid parameters
404 Not Found - Resource doesn't exist
500 Internal Server Error - Server failure
503 Service Unavailable - External service down
```

### Response Format

**Specification**:
```json
{
  "data": {
    "id": "string",
    "type": "editorial",
    "attributes": { ... }
  },
  "meta": {
    "cache": { ... },
    "requestId": "string"
  },
  "errors": []
}
```

**Current Implementation**: ⚠️ Partial compliance
- Returns flat structure (not wrapped in `data`)
- No standard `meta` or `errors` keys

### Cache Headers

**Specification**:
```
Cache-Control: public, s-maxage=64000, max-age=60, stale-while-revalidate=60
Stale-If-Error: 259200
```

**Status**: ✅ Implemented via EventSubscriber

---

## 4. Compliance Summary

| Category | Principle | Status | Notes |
|----------|-----------|--------|-------|
| Clean Code | Meaningful Names | ✅ | Good naming conventions |
| Clean Code | Small Functions | ⚠️ | Some large methods in Orchestrator |
| Clean Code | DRY | ✅ | Traits used effectively |
| SOLID | Single Responsibility | ⚠️ | EditorialOrchestrator needs split |
| SOLID | Open/Closed | ✅ | Compiler passes work well |
| SOLID | Liskov Substitution | ✅ | Interfaces consistent |
| SOLID | Interface Segregation | ✅ | Focused interfaces |
| SOLID | Dependency Inversion | ⚠️ | Thumbor violates |
| REST | URL Structure | ✅ | Follows convention |
| REST | Status Codes | ✅ | Proper codes used |
| REST | Response Format | ⚠️ | Not JSON:API standard |
| REST | Cache Headers | ✅ | Well implemented |
