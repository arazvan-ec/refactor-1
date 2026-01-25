# Project-Specific Rules - SNAAPI

**Project**: SNAAPI - API Gateway for Mobile Apps
**Framework**: Symfony 6.4
**Last Updated**: 2026-01-25

---

## Purpose

SNAAPI is an API Gateway that aggregates content from multiple microservices to serve editorial data for mobile applications. **It does NOT persist data locally** - all content is fetched via HTTP clients from external services.

---

## Tech Stack

### Backend
- **Framework**: Symfony 6.4
- **Language**: PHP 8.2+ with strict types
- **Cache**: Redis (via Symfony Cache)
- **Queue**: Symfony Messenger
- **HTTP Client**: Guzzle with Promises (async)

### External Services (Bounded Contexts)
| Context | Client | Purpose |
|---------|--------|---------|
| Editorial | `QueryEditorialClient` | News articles, blogs |
| Section | `QuerySectionClient` | Section hierarchy |
| Multimedia | `QueryMultimediaClient` | Photos, videos, widgets |
| Journalist | `QueryJournalistClient` | Author information |
| Tag | `QueryTagClient` | Content tags |
| Membership | `QueryMembershipClient` | Subscription links |
| Widget | `QueryWidgetClient` | Embedded widgets |
| Legacy | `QueryLegacyClient` | Fallback to old system |

### Infrastructure
- **CI/CD**: GitLab CI
- **Containers**: Docker
- **Image Processing**: Thumbor

---

## Architecture Patterns

### Current Architecture (DDD-inspired)
```
src/
├── Controller/          # Infrastructure: HTTP entry points (THIN!)
├── Application/         # Application Layer
│   └── DataTransformer/ # Anti-Corruption Layer (transforms external → API response)
├── Orchestrator/        # Application Layer: Aggregates multiple services
│   └── Chain/           # Chain of Responsibility pattern
├── Infrastructure/      # External services, traits, enums
│   ├── Service/         # Thumbor, PictureShots
│   ├── Trait/           # MultimediaTrait, UrlGeneratorTrait
│   └── Enum/            # EditorialTypesEnum, SitesEnum
├── EventSubscriber/     # Cache control, exception handling
├── Exception/           # Domain exceptions
└── DependencyInjection/ # Compiler passes for extensibility
```

### Request Flow
```
Controller → OrchestratorChain → EditorialOrchestrator
           → External Clients (async/promises)
           → DataTransformers (anti-corruption)
           → JSON Response
```

---

## Code Conventions

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Files | PascalCase | `EditorialOrchestrator.php` |
| Classes | PascalCase | `BodyTagPictureDataTransformer` |
| Interfaces | PascalCase + Interface suffix | `EditorialOrchestratorInterface` |
| Methods | camelCase | `canTransform()`, `execute()` |
| Variables | camelCase | `$editorialOrchestrator` |
| Constants | UPPER_SNAKE | `MAX_ITERATIONS` |
| Service Tags | kebab-case | `app.data_transformer` |
| API Endpoints | kebab-case with version | `/v1/editorials/{id}` |

### File Structure Rules
- One class per file
- File name matches class name
- Tests mirror `src/` structure in `tests/`
- Maximum file length: 300 lines (refactor if exceeded)
- DataTransformers: one per body element type

---

## Design Patterns (Required)

### 1. Chain of Responsibility (Orchestrators)
```php
// Register via Compiler Pass
OrchestratorChainHandler::handler($type, $request)
  → Routes to appropriate orchestrator
```

### 2. Strategy Pattern (DataTransformers)
```php
// Each body element type has its own transformer
interface BodyElementDataTransformerInterface {
    public function canTransform(string $type): bool;
    public function write(BodyElement $element, array $data): self;
    public function read(): array;
}
```

### 3. Template Method (Base Transformers)
```php
// ElementTypeDataTransformer provides base implementation
abstract class ElementTypeDataTransformer {
    abstract public function canTransform(string $type): bool;
    protected function write(...): self;  // Template
    protected function read(): array;     // Template
}
```

### 4. Compiler Passes (Extensibility)
```php
// Auto-register services via tags
- EditorialOrchestratorCompiler (tag: app.editorial_orchestrator)
- BodyDataTransformerCompiler (tag: app.data_transformer)
- MediaDataTransformerCompiler (tag: app.media_data_transformer)
- MultimediaOrchestratorCompiler (tag: app.multimedia_orchestrator)
```

---

## API Conventions

### URL Structure
```
/v1/editorials/{id}           # Single editorial
/v1/editorials                 # Collection (if needed)
```

### Response Format
```json
{
  "data": {
    "id": "uuid",
    "type": "editorial",
    "attributes": { ... }
  },
  "meta": {
    "cache": { ... }
  }
}
```

### Cache Headers
```
Cache-Control: public, s-maxage=64000, max-age=60, stale-while-revalidate=60
Stale-If-Error: 259200 (3 days fallback)
```

---

## Testing Requirements

### Backend
- Unit test coverage: > 80%
- PHPStan Level 9 (strict)
- Mutation testing: 79% MSI minimum
- All DataTransformers must have tests
- All Orchestrators must have tests
- Use DataProviders for parameterized tests

### Test Commands
```bash
make test_unit       # PHPUnit
make test_cs         # PHP-CS-Fixer
make test_stan       # PHPStan level 9
make test_infection  # Mutation testing
make tests           # Full suite
```

---

## Async Processing (Promises)

### Pattern for Multiple External Calls
```php
// Use Guzzle Promises for parallel requests
$promises = [
    'multimedia' => $client->findMultimediaById($id, async: true),
    'tags' => $client->findTags(async: true),
];

Utils::settle($promises)
    ->then($this->createCallback([$this, 'onFulfilled']))
    ->wait(true);
```

### Rules
- Always use promises for independent external calls
- Use `Utils::settle()` to handle failures gracefully
- Create dedicated callback methods for promise resolution
- Never block on single promise when multiple can run in parallel

---

## Anti-Corruption Layer (DataTransformers)

### Purpose
Isolate external service responses from our API response format.

### Rules
- External models (from `ec/editorial-client`, etc.) NEVER leak to API response
- DataTransformers convert external → internal representation
- Each transformer handles ONE specific type
- Register via service tags for automatic discovery

### Example
```php
// External model (from microservice)
$externalPicture = $editorialClient->getPicture();

// Transformer converts to our API format
$transformer = new BodyTagPictureDataTransformer();
$apiResponse = $transformer->write($externalPicture, $context)->read();
```

---

## Refactoring Targets

### Priority 1: EditorialOrchestrator Complexity
- **Current**: 537 lines, multiple responsibilities
- **Target**: Split into smaller collaborators
- **Actions**:
  1. Extract `PromiseResolutionStrategy`
  2. Create `EmbeddedEditorialsFetcher` for inserted/recommended
  3. Simplify `execute()` method

### Priority 2: Type Hints
- **Current**: Excessive `array<string, mixed>`
- **Target**: Typed DTOs for responses
- **Actions**:
  1. Create response DTOs
  2. Use Value Objects where appropriate
  3. Remove `mixed` types where possible

### Priority 3: Error Handling
- **Current**: Generic try-catch, dispersed logging
- **Target**: Centralized domain exceptions
- **Actions**:
  1. Create specific domain exceptions
  2. Centralize error handling strategy
  3. Improve observability (structured logging)

### Priority 4: Namespace Organization
- **Current**: `Ec/Snaapi` is confusing
- **Target**: Clear DDD layer separation
- **Actions**:
  1. Move `QueryLegacyClient` to `Infrastructure/Client/Legacy/`
  2. Remove unnecessary namespace nesting

---

## Reference Code (Patterns to Follow)

| Pattern | Location | Description |
|---------|----------|-------------|
| Thin Controller | `src/Controller/V1/EditorialController.php` | Entry point pattern |
| Chain Handler | `src/Orchestrator/OrchestratorChainHandler.php` | Chain of Responsibility |
| Orchestrator | `src/Orchestrator/Chain/EditorialOrchestrator.php` | Service aggregation |
| DataTransformer | `src/Application/DataTransformer/Apps/Body/ParagraphDataTransformer.php` | Strategy pattern |
| Base Transformer | `src/Application/DataTransformer/Apps/Body/ElementTypeDataTransformer.php` | Template method |
| Compiler Pass | `src/DependencyInjection/Compiler/BodyDataTransformerCompiler.php` | Auto-registration |
| Trait | `src/Infrastructure/Trait/MultimediaTrait.php` | Reusable behavior |

---

## Ubiquitous Language

| Term | Meaning |
|------|---------|
| **Editorial** | A news article or blog post |
| **Section** | Category hierarchy (e.g., Sports > Football) |
| **Multimedia** | Photos, videos, or widgets attached to content |
| **Journalist** | Author of an editorial |
| **Tag** | Keyword/topic associated with content |
| **Signature** | Byline information |
| **Body** | Main content of editorial (paragraphs, images, etc.) |
| **BodyTag** | Special element in body (picture, video, inserted news) |
| **Standfirst** | Lead paragraph / summary |
| **Orchestrator** | Service that coordinates multiple external calls |
| **DataTransformer** | Converts external model to API response format |

---

## Security Requirements

### API Security
- No authentication (public API for apps)
- Rate limiting at infrastructure level (nginx/CDN)
- Input validation on path parameters (editorial ID format)

### Data Protection
- No PII stored locally (all from external services)
- HTTPS required
- No sensitive data in logs

---

## Performance Requirements

### Backend
- API response time: < 200ms (p95)
- External service calls: parallelized via promises
- No N+1 queries (not applicable - no DB)
- Cache headers properly set

### Caching Strategy
- CDN caching via Cache-Control headers
- Stale-while-revalidate for background refresh
- Stale-if-error for graceful degradation

---

**Note**: This project is an API Gateway without local persistence. All domain logic resides in external microservices. Our responsibility is aggregation, transformation, and caching.

**Last Updated**: 2026-01-25
**Updated By**: Workflow Setup
