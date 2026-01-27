# Plan: Layer Architecture Rules - SNAAPI

**Fecha**: 2026-01-27
**Status**: ✅ COMPLETED
**Objetivo**: Definir reglas claras para cada capa arquitectónica y mecanismos de enforcement

---

## Resumen Ejecutivo

~~El análisis revela **violaciones críticas** de separación de capas:~~
~~- `EditorialFetcher` y `EmbeddedContentFetcher` (Application layer) hacen HTTP calls~~
~~- Debería estar solo en Orchestrator layer~~

**✅ COMPLETADO**: Todas las violaciones han sido corregidas:
- `EditorialFetcher` movido a `Orchestrator/Service/` (Phase 2)
- `EmbeddedContentFetcher` movido a `Orchestrator/Service/` (Phase 3)
- Tests de arquitectura implementados para todas las capas (Phase 1)
- Documentación actualizada (Phase 4)

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

## 3. Violaciones Corregidas

### ✅ Críticas (RESUELTAS)

| Clase | Layer Original | Problema | Acción | Estado |
|-------|----------------|----------|--------|--------|
| `EditorialFetcher` | Application | Inyectaba 3 HTTP clients | Movido a `Orchestrator/Service/` | ✅ Phase 2 |
| `EmbeddedContentFetcher` | Application | Inyectaba 6 HTTP clients | Movido a `Orchestrator/Service/` | ✅ Phase 3 |

### ⚠️ Moderadas (Pendiente para futuro)

| Clase | Layer Actual | Problema | Acción |
|-------|--------------|----------|--------|
| `EditorialOrchestrator` | Orchestrator | 11 dependencias | Extraer más fetchers (mejora futura) |

---

## 4. Tests de Arquitectura Implementados ✅

Todos los tests de arquitectura han sido implementados en `tests/Architecture/`:

### 4.1 AbstractArchitectureTest (Base Class)
```php
// Base class with common functionality for detecting forbidden dependencies
// Location: tests/Architecture/AbstractArchitectureTest.php
```

### 4.2 Tests Implementados

| Test | Ubicación | Verifica |
|------|-----------|----------|
| `TransformationLayerArchitectureTest` | Existente | DataTransformers y Aggregators NO inyectan *Client |
| `ControllerLayerArchitectureTest` | ✅ Phase 1 | Controllers solo inyectan OrchestratorChain |
| `ApplicationServiceArchitectureTest` | ✅ Phase 1 | Application Services NO inyectan *Client |
| `InfrastructureServiceArchitectureTest` | ✅ Phase 1 | Infrastructure Services NO inyectan *Client |
| `EventSubscriberArchitectureTest` | ✅ Phase 1 | EventSubscribers NO inyectan *Client |
| `ExceptionArchitectureTest` | ✅ Phase 1 | Exceptions NO tienen dependencias de servicios |

**Ejecutar tests**: `./bin/phpunit --group architecture`

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

## 6. Plan de Implementación ✅ COMPLETADO

### ✅ Fase 1: Tests de Arquitectura
**Commit**: `test(architecture): add comprehensive architecture tests for all layers`
1. ✅ Creado `AbstractArchitectureTest` como base class
2. ✅ Creado `ApplicationServiceArchitectureTest`
3. ✅ Creado `ControllerLayerArchitectureTest`
4. ✅ Creado `InfrastructureServiceArchitectureTest`
5. ✅ Creado `EventSubscriberArchitectureTest`
6. ✅ Creado `ExceptionArchitectureTest`

### ✅ Fase 2: Refactorizar EditorialFetcher
**Commit**: `refactor(architecture): move EditorialFetcher to Orchestrator layer`
1. ✅ Movido `EditorialFetcher` a `Orchestrator/Service/`
2. ✅ Movido `EditorialFetcherInterface` a `Orchestrator/Service/`
3. ✅ Actualizado import en `EditorialOrchestrator`
4. ✅ Actualizado `orchestrators.yaml`

### ✅ Fase 3: Refactorizar EmbeddedContentFetcher
**Commit**: `refactor(architecture): move EmbeddedContentFetcher to Orchestrator layer`
1. ✅ Movido `EmbeddedContentFetcher` a `Orchestrator/Service/`
2. ✅ Movido `EmbeddedContentFetcherInterface` a `Orchestrator/Service/`
3. ✅ Actualizado import en `EditorialOrchestrator`
4. ✅ Actualizado `orchestrators.yaml`
5. ✅ Limpiado known violations en architecture tests

### ✅ Fase 4: Documentación
**Commit**: `docs(architecture): complete layer architecture documentation`
1. ✅ Actualizado este documento con status de completado
2. ✅ Actualizado `project_specific.md` con reglas finales

**Total real**: ~2 horas (más rápido de lo estimado)

---

## 7. Criterios de Éxito ✅

- [x] Todos los tests de arquitectura pasan
- [x] Ninguna clase en Application inyecta `*Client`
- [x] Ninguna clase en Infrastructure\Service inyecta `*Client`
- [x] Controllers solo inyectan OrchestratorChain
- [x] EventSubscribers no inyectan `*Client`
- [x] Documentación actualizada

---

## 8. Próximos Pasos (Mejoras Futuras)

El plan ha sido completado exitosamente. Posibles mejoras futuras:

1. **Reducir dependencias en EditorialOrchestrator** (11 → 7-8)
   - Extraer más fetchers especializados
   - Simplificar el flujo de promises

2. **Añadir más tests de arquitectura**
   - Test de dependencias circulares
   - Test de profundidad de herencia
   - Test de complejidad ciclomática

3. **CI/CD Integration**
   - Añadir `--group architecture` al pipeline
   - Fail fast en violaciones arquitectónicas

---

**Autor**: Claude (Compound Engineering)
**Fecha**: 2026-01-27
**Version**: 2.0 (COMPLETED)
**Completado**: 2026-01-27
