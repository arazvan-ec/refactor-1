# Plan: Layer Architecture Rules - SNAAPI

**Fecha**: 2026-01-27
**Status**: PLAN
**Objetivo**: Definir reglas claras para cada capa arquitectónica y mecanismos de enforcement

---

## Resumen Ejecutivo

El análisis revela **violaciones críticas** de separación de capas:
- `EditorialFetcher` y `EmbeddedContentFetcher` (Application layer) hacen HTTP calls
- Debería estar solo en Orchestrator layer

**Acción propuesta**: Definir reglas estrictas por capa + tests de arquitectura para enforcement.

---

## 1. Definición de Capas

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CONTROLLER LAYER                            │
│  Responsabilidad: HTTP entry point, routing, response wrapping      │
│  Ubicación: src/Controller/                                         │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        ORCHESTRATOR LAYER                           │
│  Responsabilidad: Coordina HTTP calls, maneja promises, flujo       │
│  Ubicación: src/Orchestrator/                                       │
│  ÚNICO LUGAR donde se permiten HTTP calls                           │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        APPLICATION LAYER                            │
│  Responsabilidad: Transformación, agregación, DTOs                  │
│  Ubicación: src/Application/                                        │
│  NO puede hacer HTTP calls                                          │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       INFRASTRUCTURE LAYER                          │
│  Responsabilidad: Servicios técnicos, traits, enums, config         │
│  Ubicación: src/Infrastructure/                                     │
│  HTTP clients viven aquí pero NO se llaman desde aquí               │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 2. Reglas por Capa

### 2.1 Controller Layer (`src/Controller/`)

| Categoría | Regla |
|-----------|-------|
| **CAN** | Recibir Request HTTP |
| **CAN** | Validar parámetros de ruta |
| **CAN** | Delegar a Orchestrator |
| **CAN** | Devolver Response HTTP |
| **CANNOT** | Inyectar HTTP clients |
| **CANNOT** | Contener lógica de negocio |
| **CANNOT** | Transformar datos |
| **CANNOT** | Acceder directamente a Application services |

**Patrón requerido:**
```php
class EditorialController {
    public function __construct(
        private OrchestratorChain $orchestrator  // ✅ Solo orchestrator
    ) {}

    public function __invoke(Request $request): JsonResponse {
        $data = $this->orchestrator->handler('editorial', $request);
        return new JsonResponse($data);
    }
}
```

**Test de arquitectura:**
- Controllers solo inyectan `OrchestratorChain` o `OrchestratorChainHandler`
- No inyectan `*Client`, `*Fetcher`, `*Transformer`

---

### 2.2 Orchestrator Layer (`src/Orchestrator/`)

| Categoría | Regla |
|-----------|-------|
| **CAN** | Inyectar HTTP clients (`*Client`) |
| **CAN** | Hacer llamadas HTTP |
| **CAN** | Manejar promises async |
| **CAN** | Coordinar múltiples fetches |
| **CAN** | Inyectar Application services para agregación |
| **CAN** | Inyectar Transformers para formateo |
| **CANNOT** | Contener lógica de transformación de datos |
| **CANNOT** | Formatear responses directamente |
| **CANNOT** | Acceder a Controller |

**Sub-capas dentro de Orchestrator:**

#### 2.2.1 Chain Orchestrators (`src/Orchestrator/Chain/`)
```php
class EditorialOrchestrator implements EditorialOrchestratorInterface {
    public function __construct(
        // ✅ HTTP Clients - OK aquí
        private QueryEditorialClient $editorialClient,
        private QueryTagClient $tagClient,

        // ✅ Fetchers especializados - OK
        private SignatureFetcherInterface $signatureFetcher,
        private CommentsFetcherInterface $commentsFetcher,

        // ✅ Aggregators/Transformers - para delegar formato
        private ResponseAggregatorInterface $responseAggregator,
    ) {}

    public function execute(Request $request): array {
        // Fetch (HTTP calls)
        $editorial = $this->editorialClient->find($id);
        $tags = $this->fetchTags($editorial);

        // Pre-fetch external data
        $preFetchedData = new PreFetchedDataDTO(
            commentsCount: $this->commentsFetcher->fetchCommentsCount($id),
            signatures: $this->signatureFetcher->fetchSignatures($editorial),
        );

        // Delegate transformation (NO HTTP here)
        return $this->responseAggregator->aggregate(..., $preFetchedData);
    }
}
```

#### 2.2.2 Service Fetchers (`src/Orchestrator/Service/`)
```php
// Servicios que HACEN HTTP calls + opcionalmente transforman
class SignatureFetcher implements SignatureFetcherInterface {
    public function __construct(
        private QueryJournalistClient $client,     // ✅ HTTP OK
        private JournalistsDataTransformer $transformer,  // ✅ Para formatear
    ) {}

    public function fetchSignatures(...): array {
        $journalist = $this->client->findByAliasId($id);  // HTTP call
        return $this->transformer->write(...)->read();     // Transform
    }
}
```

**Test de arquitectura:**
- Orchestrators pueden inyectar `*Client`
- Orchestrators pueden inyectar `*Fetcher`, `*Aggregator`, `*Transformer`
- Orchestrator/Service/* pueden inyectar `*Client`

---

### 2.3 Application Layer (`src/Application/`)

| Categoría | Regla |
|-----------|-------|
| **CAN** | Transformar estructuras de datos |
| **CAN** | Agregar datos ya fetched |
| **CAN** | Crear y usar DTOs |
| **CAN** | Usar Infrastructure services (Thumbor, PictureShots) |
| **CANNOT** | Inyectar HTTP clients (`*Client`) |
| **CANNOT** | Hacer llamadas HTTP |
| **CANNOT** | Manejar promises directamente |

**Sub-capas:**

#### 2.3.1 DataTransformers (`src/Application/DataTransformer/`)
```php
class BodyTagPictureDataTransformer implements BodyElementDataTransformerInterface {
    public function __construct(
        private PictureShots $pictureShots,  // ✅ Infrastructure service OK
        // ❌ NO: QueryMultimediaClient
    ) {}

    public function write(BodyElement $element, array $resolveData): self {
        // Solo transforma, no fetcha
        $this->data = $this->pictureShots->retrieveShots($resolveData, $element);
        return $this;
    }

    public function read(): array {
        return $this->data;
    }
}
```

#### 2.3.2 Services/Aggregators (`src/Application/Service/`)
```php
class ResponseAggregator implements ResponseAggregatorInterface {
    public function __construct(
        private AppsDataTransformer $transformer,      // ✅ OK
        private BodyDataTransformer $bodyTransformer,  // ✅ OK
        // ❌ NO: QueryLegacyClient
        // ❌ NO: QueryJournalistClient
    ) {}

    public function aggregate(
        FetchedEditorialDTO $editorial,
        PreFetchedDataDTO $preFetchedData,  // ✅ Datos ya fetched
    ): array {
        // Solo agrega/transforma, no fetcha
        $result = $this->transformer->write(...)->read();
        $result['countComments'] = $preFetchedData->commentsCount;
        return $result;
    }
}
```

#### 2.3.3 DTOs (`src/Application/DTO/`)
```php
// Solo contenedores de datos, sin lógica ni dependencias
final readonly class PreFetchedDataDTO {
    public function __construct(
        public int $commentsCount,
        public array $signatures,
    ) {}
}
```

**Test de arquitectura:**
- `Application\DataTransformer\*` NO puede inyectar `*Client`
- `Application\Service\*Aggregator` NO puede inyectar `*Client`
- `Application\DTO\*` no tiene constructor con dependencias de servicios

---

### 2.4 Infrastructure Layer (`src/Infrastructure/`)

| Categoría | Regla |
|-----------|-------|
| **CAN** | Definir HTTP clients |
| **CAN** | Proveer servicios técnicos (Thumbor, URL generation) |
| **CAN** | Definir traits reutilizables |
| **CAN** | Definir enums y configuración |
| **CANNOT** | Hacer HTTP calls directamente (excepto Client implementations) |
| **CANNOT** | Contener lógica de negocio |
| **CANNOT** | Depender de Application o Orchestrator |

**Sub-capas:**

#### 2.4.1 Clients (`src/Infrastructure/Client/`)
```php
// Define cómo hacer HTTP calls, pero no los ejecuta solo
class QueryLegacyClient {
    public function findCommentsByEditorialId(string $id): array {
        // HTTP call implementation
    }
}
```

#### 2.4.2 Services (`src/Infrastructure/Service/`)
```php
class Thumbor {
    // Construye URLs, NO hace HTTP calls
    public function retriveCropBodyTagPicture(...): string {
        return $this->buildUrl(...);  // Solo string manipulation
    }
}

class PictureShots {
    public function __construct(
        private Thumbor $thumbor,  // ✅ OK - Infrastructure service
        // ❌ NO: QueryMultimediaClient - no HTTP aquí
    ) {}
}
```

#### 2.4.3 Config (`src/Infrastructure/Config/`)
```php
// Solo constantes y configuración estática
final class MultimediaImageSizes {
    public const SIZES_RELATIONS = [...];
    public const BODY_TAG_SIZES_RELATIONS = [...];
}
```

**Test de arquitectura:**
- `Infrastructure\Service\*` NO puede inyectar `*Client` (excepto si ES un Client)
- `Infrastructure\Config\*` no tiene constructor
- `Infrastructure\Trait\*` no inyecta nada

---

### 2.5 EventSubscriber Layer (`src/EventSubscriber/`)

| Categoría | Regla |
|-----------|-------|
| **CAN** | Interceptar eventos del kernel |
| **CAN** | Transformar excepciones a responses |
| **CAN** | Agregar headers (cache, etc.) |
| **CAN** | Loggear |
| **CANNOT** | Hacer HTTP calls |
| **CANNOT** | Contener lógica de negocio |
| **CANNOT** | Modificar datos de dominio |

```php
class ExceptionSubscriber implements EventSubscriberInterface {
    public function __construct(
        private string $appEnv,
        private ?LoggerInterface $logger,  // ✅ OK
        // ❌ NO: QueryEditorialClient
    ) {}
}
```

**Test de arquitectura:**
- `EventSubscriber\*` NO puede inyectar `*Client`

---

### 2.6 Exception Layer (`src/Exception/`)

| Categoría | Regla |
|-----------|-------|
| **CAN** | Definir excepciones de dominio |
| **CAN** | Llevar código de error y mensaje |
| **CAN** | Especificar HTTP status code |
| **CANNOT** | Tener dependencias |
| **CANNOT** | Hacer HTTP calls |
| **CANNOT** | Contener lógica |

```php
class EditorialNotFoundException extends AbstractDomainException {
    public function __construct(string $id) {
        parent::__construct("Editorial {$id} not found");
    }

    public function getStatusCode(): int { return 404; }
    public function getErrorCode(): string { return 'EDITORIAL_NOT_FOUND'; }
}
```

**Test de arquitectura:**
- `Exception\*` no tiene constructor con servicios inyectados

---

## 3. Violaciones Actuales a Corregir

### 🔴 Críticas

| Clase | Layer Actual | Problema | Acción |
|-------|--------------|----------|--------|
| `EditorialFetcher` | Application | Inyecta 3 HTTP clients | Mover lógica a Orchestrator |
| `EmbeddedContentFetcher` | Application | Inyecta 6 HTTP clients | Dividir en Orchestrator services |

### ⚠️ Moderadas

| Clase | Layer Actual | Problema | Acción |
|-------|--------------|----------|--------|
| `EditorialOrchestrator` | Orchestrator | 11 dependencias | Extraer más fetchers |

---

## 4. Tests de Arquitectura Propuestos

### 4.1 Test Existente (ya implementado)
```php
// TransformationLayerArchitectureTest
// Verifica: DataTransformers y Aggregators NO inyectan *Client
```

### 4.2 Tests Nuevos a Crear

#### Test: ControllerLayerArchitectureTest
```php
/**
 * Controllers solo pueden inyectar OrchestratorChain(Handler)
 */
public function test_controllers_only_inject_orchestrator(): void
```

#### Test: ApplicationServiceArchitectureTest
```php
/**
 * Application\Service\* NO puede inyectar *Client
 * (Extiende TransformationLayerArchitectureTest a incluir Services)
 */
public function test_application_services_do_not_inject_http_clients(): void
```

#### Test: InfrastructureServiceArchitectureTest
```php
/**
 * Infrastructure\Service\* NO puede inyectar *Client
 * Excepto Infrastructure\Client\* que SÍ puede
 */
public function test_infrastructure_services_do_not_inject_http_clients(): void
```

#### Test: EventSubscriberArchitectureTest
```php
/**
 * EventSubscriber\* NO puede inyectar *Client
 */
public function test_event_subscribers_do_not_inject_http_clients(): void
```

#### Test: ExceptionArchitectureTest
```php
/**
 * Exception\* NO tiene constructor con servicios
 */
public function test_exceptions_have_no_service_dependencies(): void
```

---

## 5. Matriz de Dependencias Permitidas

| From \ To | Controller | Orchestrator | Application | Infrastructure | EventSubscriber | Exception |
|-----------|------------|--------------|-------------|----------------|-----------------|-----------|
| **Controller** | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ |
| **Orchestrator** | ❌ | ✅ | ✅ | ✅ | ❌ | ✅ |
| **Application** | ❌ | ❌ | ✅ | ✅ | ❌ | ✅ |
| **Infrastructure** | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ |
| **EventSubscriber** | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Exception** | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |

**Leyenda:**
- ✅ = Puede depender de
- ❌ = NO puede depender de

---

## 6. Plan de Implementación

### Fase 1: Tests de Arquitectura (2h)
1. Extender `TransformationLayerArchitectureTest` para incluir `Application\Service\*`
2. Crear `ControllerLayerArchitectureTest`
3. Crear `InfrastructureServiceArchitectureTest`

### Fase 2: Refactorizar EditorialFetcher (3h)
1. Crear `EditorialFetchingService` en Orchestrator/Service
2. Mover HTTP calls de EditorialFetcher a nuevo service
3. EditorialFetcher pasa a ser solo DTOs/helpers

### Fase 3: Refactorizar EmbeddedContentFetcher (4h)
1. Crear `InsertedNewsFetcher` en Orchestrator/Service
2. Crear `RecommendedNewsFetcher` en Orchestrator/Service
3. Crear `MultimediaFetcher` en Orchestrator/Service
4. EmbeddedContentFetcher coordina pero no hace HTTP

### Fase 4: Documentación (1h)
1. Actualizar project_specific.md con todas las reglas
2. Actualizar CLAUDE.md con diagrama de capas

**Total estimado**: 10 horas

---

## 7. Criterios de Éxito

- [ ] Todos los tests de arquitectura pasan
- [ ] Ninguna clase en Application inyecta `*Client`
- [ ] Ninguna clase en Infrastructure\Service inyecta `*Client`
- [ ] Controllers solo inyectan OrchestratorChain
- [ ] EventSubscribers no inyectan `*Client`
- [ ] Documentación actualizada

---

## 8. Próximos Pasos Inmediatos

1. **Revisar este plan** con el equipo
2. **Priorizar** qué tests de arquitectura crear primero
3. **Decidir** si refactorizar EditorialFetcher ahora o después
4. **Crear issue/ticket** para cada fase

---

**Autor**: Claude (Compound Engineering)
**Fecha**: 2026-01-27
**Version**: 1.0
