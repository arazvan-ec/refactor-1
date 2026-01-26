# Logging & Observability Improvement Plan

**Project**: SNAAPI
**Date**: 2026-01-26
**Status**: PLAN

---

## 1. Current State Analysis

### What's GOOD

| Component | Status |
|-----------|--------|
| Elasticsearch integration | ✅ Monolog sends to ES |
| HTTPlug logger plugin | ✅ Logs all HTTP calls |
| ExceptionSubscriber logging | ✅ Excellent structured logs |
| Custom formatter | ✅ EcMonologFormatter |

### What NEEDS IMPROVEMENT

| Issue | Location | Impact |
|-------|----------|--------|
| No request tracing | All services | Can't correlate logs |
| Inconsistent log structure | Services | Hard to query |
| No performance metrics | Orchestrators | Can't measure latency |
| Missing business context | Transformers | Can't debug issues |

---

## 2. Proposed Observability Architecture

### Three Pillars

```
┌─────────────────────────────────────────────────────────────┐
│                    OBSERVABILITY                            │
├─────────────────┬─────────────────┬─────────────────────────┤
│     LOGS        │    METRICS      │       TRACES            │
│  (Elasticsearch)│   (Prometheus)  │      (Jaeger)           │
├─────────────────┼─────────────────┼─────────────────────────┤
│ Structured JSON │ Counters/Gauges │ Request correlation     │
│ Error details   │ Latency histog. │ Service dependencies    │
│ Business events │ Cache hit rates │ Bottleneck detection    │
└─────────────────┴─────────────────┴─────────────────────────┘
```

### Focus for This Plan: LOGS + Basic METRICS

---

## 3. Request Correlation (Tracing)

### 3.1 Request ID Middleware

```php
// src/Infrastructure/Middleware/RequestIdMiddleware.php
namespace App\Infrastructure\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RequestIdMiddleware implements HttpKernelInterface
{
    public function __construct(
        private readonly HttpKernelInterface $kernel,
    ) {}

    public function handle(
        Request $request,
        int $type = self::MAIN_REQUEST,
        bool $catch = true
    ): Response {
        // Generate or use existing request ID
        $requestId = $request->headers->get('X-Request-ID')
            ?? $this->generateRequestId();

        // Store in request attributes
        $request->attributes->set('request_id', $requestId);

        // Add to response
        $response = $this->kernel->handle($request, $type, $catch);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    private function generateRequestId(): string
    {
        return sprintf(
            '%s-%s',
            substr(bin2hex(random_bytes(4)), 0, 8),
            time()
        );
    }
}
```

### 3.2 Monolog Processor for Request ID

```php
// src/Infrastructure/Logging/RequestIdProcessor.php
namespace App\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestIdProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return $record;
        }

        $extra = $record->extra;
        $extra['request_id'] = $request->attributes->get('request_id', 'cli');
        $extra['path'] = $request->getPathInfo();
        $extra['method'] = $request->getMethod();

        return $record->with(extra: $extra);
    }
}
```

### 3.3 Configuration

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        main:
            type: fingers_crossed
            action_level: error
            handler: elastic_search
            processors:
                - App\Infrastructure\Logging\RequestIdProcessor
```

### 3.4 Result: Correlated Logs

```json
// All logs from same request have same request_id
{
  "message": "Editorial fetched",
  "context": { "editorial_id": "123" },
  "extra": {
    "request_id": "a1b2c3d4-1706300000",
    "path": "/v1/editorials/123",
    "method": "GET"
  }
}

{
  "message": "Photo fetch failed",
  "context": { "photo_id": "456", "error_code": "ERR_1004" },
  "extra": {
    "request_id": "a1b2c3d4-1706300000",  // Same request!
    "path": "/v1/editorials/123",
    "method": "GET"
  }
}
```

---

## 4. Structured Logging Standard

### 4.1 Log Structure Schema

```php
// Every log MUST follow this structure
$this->logger->info('Action description', [
    // REQUIRED for errors
    'error_code' => 'ERR_1001',

    // REQUIRED for resource operations
    'resource_type' => 'editorial|photo|multimedia|etc',
    'resource_id' => 'the-id',

    // OPTIONAL but recommended
    'operation' => 'fetch|transform|resolve|etc',
    'duration_ms' => 45,

    // OPTIONAL context
    'context' => [
        'parent_id' => '...',
        'additional' => '...',
    ],
]);
```

### 4.2 Logging Trait

```php
// src/Infrastructure/Logging/LoggingTrait.php
namespace App\Infrastructure\Logging;

use Psr\Log\LoggerInterface;

trait LoggingTrait
{
    private ?LoggerInterface $logger = null;

    protected function logOperation(
        string $operation,
        string $resourceType,
        ?string $resourceId,
        array $context = [],
    ): void {
        $this->logger?->debug("Operation: {$operation}", [
            'operation' => $operation,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'context' => $context,
        ]);
    }

    protected function logOperationSuccess(
        string $operation,
        string $resourceType,
        ?string $resourceId,
        float $durationMs,
        array $context = [],
    ): void {
        $this->logger?->info("Completed: {$operation}", [
            'operation' => $operation,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'duration_ms' => round($durationMs, 2),
            'status' => 'success',
            'context' => $context,
        ]);
    }

    protected function logOperationFailure(
        string $operation,
        string $resourceType,
        ?string $resourceId,
        string $errorCode,
        \Throwable $exception,
        array $context = [],
    ): void {
        $this->logger?->warning("Failed: {$operation}", [
            'operation' => $operation,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'error_code' => $errorCode,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'status' => 'failed',
            'context' => $context,
        ]);
    }
}
```

### 4.3 Usage

```php
final class EmbeddedContentFetcher
{
    use LoggingTrait;

    private function fetchEmbeddedEditorial(string $editorialId): ?array
    {
        $startTime = microtime(true);

        $this->logOperation('fetch_embedded_editorial', 'editorial', $editorialId);

        try {
            $result = $this->editorialClient->findEditorialById($editorialId);

            $this->logOperationSuccess(
                operation: 'fetch_embedded_editorial',
                resourceType: 'editorial',
                resourceId: $editorialId,
                durationMs: (microtime(true) - $startTime) * 1000,
            );

            return $result;

        } catch (\Throwable $e) {
            $this->logOperationFailure(
                operation: 'fetch_embedded_editorial',
                resourceType: 'editorial',
                resourceId: $editorialId,
                errorCode: ErrorCode::EDITORIAL_FETCH_FAILED,
                exception: $e,
            );

            return null;
        }
    }
}
```

---

## 5. Performance Logging

### 5.1 Timer Helper

```php
// src/Infrastructure/Logging/Timer.php
namespace App\Infrastructure\Logging;

final class Timer
{
    private float $startTime;
    private array $checkpoints = [];

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function checkpoint(string $name): void
    {
        $this->checkpoints[$name] = microtime(true);
    }

    public function elapsed(): float
    {
        return (microtime(true) - $this->startTime) * 1000;
    }

    public function getCheckpoints(): array
    {
        $result = [];
        $previous = $this->startTime;

        foreach ($this->checkpoints as $name => $time) {
            $result[$name] = round(($time - $previous) * 1000, 2);
            $previous = $time;
        }

        $result['total'] = round($this->elapsed(), 2);

        return $result;
    }
}
```

### 5.2 Orchestrator with Performance Logging

```php
final class EditorialOrchestrator implements EditorialOrchestratorInterface
{
    public function execute(Request $request): array
    {
        $timer = new Timer();
        $id = $request->attributes->get('id');

        // 1. Fetch editorial
        $fetchedEditorial = $this->editorialFetcher->fetch($id);
        $timer->checkpoint('editorial_fetch');

        // 2. Collect body requirements
        $bodyRequirements = $this->bodyElementDataCollector->collect(
            $fetchedEditorial->editorial->body()
        );
        $timer->checkpoint('body_collect');

        // 3. Fetch embedded content
        $embeddedContent = $this->embeddedContentFetcher->fetch(
            $fetchedEditorial->editorial,
            $fetchedEditorial->section
        );
        $timer->checkpoint('embedded_fetch');

        // 4. Resolve promises
        $resolvedData = $this->promiseResolver->resolveAll(...);
        $timer->checkpoint('promise_resolve');

        // 5. Aggregate response
        $result = $this->responseAggregator->aggregate(...);
        $timer->checkpoint('aggregate');

        // Log performance breakdown
        $this->logger->info('Editorial orchestration completed', [
            'resource_type' => 'editorial',
            'resource_id' => $id,
            'operation' => 'orchestrate',
            'timings_ms' => $timer->getCheckpoints(),
        ]);

        return $result;
    }
}
```

### 5.3 Example Log Output

```json
{
  "message": "Editorial orchestration completed",
  "context": {
    "resource_type": "editorial",
    "resource_id": "abc-123",
    "operation": "orchestrate",
    "timings_ms": {
      "editorial_fetch": 45.23,
      "body_collect": 2.10,
      "embedded_fetch": 35.67,
      "promise_resolve": 89.44,
      "aggregate": 12.33,
      "total": 184.77
    }
  },
  "extra": {
    "request_id": "a1b2c3d4-1706300000"
  }
}
```

---

## 6. Business Event Logging

### 6.1 Business Events

```php
// src/Infrastructure/Logging/BusinessEvent.php
namespace App\Infrastructure\Logging;

final class BusinessEvent
{
    public const EDITORIAL_SERVED = 'editorial.served';
    public const EDITORIAL_NOT_FOUND = 'editorial.not_found';
    public const EDITORIAL_NOT_PUBLISHED = 'editorial.not_published';
    public const CACHE_HIT = 'cache.hit';
    public const CACHE_MISS = 'cache.miss';
    public const GRACEFUL_DEGRADATION = 'service.degraded';
    public const PROMISE_BATCH_RESOLVED = 'promises.resolved';
}
```

### 6.2 Business Event Logger

```php
// src/Infrastructure/Logging/BusinessEventLogger.php
namespace App\Infrastructure\Logging;

use Psr\Log\LoggerInterface;

final class BusinessEventLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function log(
        string $event,
        array $data = [],
    ): void {
        $this->logger->info($event, [
            'event' => $event,
            'event_data' => $data,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]);
    }

    public function editorialServed(
        string $editorialId,
        string $sectionSlug,
        float $durationMs,
        bool $fromCache,
    ): void {
        $this->log(BusinessEvent::EDITORIAL_SERVED, [
            'editorial_id' => $editorialId,
            'section_slug' => $sectionSlug,
            'duration_ms' => round($durationMs, 2),
            'from_cache' => $fromCache,
        ]);
    }

    public function promisesBatchResolved(
        int $total,
        int $fulfilled,
        int $rejected,
        float $durationMs,
    ): void {
        $this->log(BusinessEvent::PROMISE_BATCH_RESOLVED, [
            'total_promises' => $total,
            'fulfilled' => $fulfilled,
            'rejected' => $rejected,
            'success_rate' => $total > 0 ? round($fulfilled / $total * 100, 2) : 0,
            'duration_ms' => round($durationMs, 2),
        ]);
    }

    public function gracefulDegradation(
        string $service,
        string $resourceType,
        ?string $resourceId,
        string $reason,
    ): void {
        $this->log(BusinessEvent::GRACEFUL_DEGRADATION, [
            'service' => $service,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'reason' => $reason,
        ]);
    }
}
```

---

## 7. HTTPlug Enhanced Logging

### 7.1 Custom Logger Plugin

```php
// src/Infrastructure/Http/LoggingPlugin.php
namespace App\Infrastructure\Http;

use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class LoggingPlugin implements Plugin
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function handleRequest(
        RequestInterface $request,
        callable $next,
        callable $first
    ): Promise {
        $startTime = microtime(true);
        $method = $request->getMethod();
        $uri = (string) $request->getUri();

        return $next($request)->then(
            function (ResponseInterface $response) use ($startTime, $method, $uri) {
                $duration = (microtime(true) - $startTime) * 1000;

                $this->logger->info('HTTP request completed', [
                    'http_method' => $method,
                    'http_uri' => $uri,
                    'http_status' => $response->getStatusCode(),
                    'duration_ms' => round($duration, 2),
                    'response_size' => $response->getBody()->getSize(),
                ]);

                return $response;
            },
            function (\Throwable $exception) use ($startTime, $method, $uri) {
                $duration = (microtime(true) - $startTime) * 1000;

                $this->logger->error('HTTP request failed', [
                    'http_method' => $method,
                    'http_uri' => $uri,
                    'duration_ms' => round($duration, 2),
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        );
    }
}
```

### 7.2 Register as Service

```yaml
# config/services.yaml
services:
    App\Infrastructure\Http\LoggingPlugin:
        arguments:
            $logger: '@logger'
        tags:
            - { name: 'httplug.plugin', alias: 'app_logging' }
```

```yaml
# config/packages/httplug.yaml
httplug:
    clients:
        app_guzzle7:
            plugins:
                - 'httplug.plugin.cache'
                - 'httplug.plugin.app_logging'  # Our custom plugin
                - 'httplug.plugin.retry'
```

---

## 8. Elasticsearch Index Template

### 8.1 Index Mapping

```json
{
  "index_patterns": ["snaapi-logs-*"],
  "template": {
    "settings": {
      "number_of_shards": 1,
      "number_of_replicas": 1
    },
    "mappings": {
      "properties": {
        "message": { "type": "text" },
        "level": { "type": "keyword" },
        "channel": { "type": "keyword" },
        "datetime": { "type": "date" },
        "context": {
          "properties": {
            "error_code": { "type": "keyword" },
            "resource_type": { "type": "keyword" },
            "resource_id": { "type": "keyword" },
            "operation": { "type": "keyword" },
            "duration_ms": { "type": "float" },
            "status": { "type": "keyword" },
            "event": { "type": "keyword" },
            "http_method": { "type": "keyword" },
            "http_status": { "type": "integer" },
            "http_uri": { "type": "keyword" }
          }
        },
        "extra": {
          "properties": {
            "request_id": { "type": "keyword" },
            "path": { "type": "keyword" },
            "method": { "type": "keyword" }
          }
        }
      }
    }
  }
}
```

---

## 9. Kibana Dashboard Queries

### 9.1 Request Latency

```json
{
  "query": {
    "bool": {
      "must": [
        { "match": { "context.operation": "orchestrate" } },
        { "range": { "datetime": { "gte": "now-1h" } } }
      ]
    }
  },
  "aggs": {
    "avg_latency": {
      "avg": { "field": "context.timings_ms.total" }
    },
    "p95_latency": {
      "percentiles": {
        "field": "context.timings_ms.total",
        "percents": [95]
      }
    }
  }
}
```

### 9.2 Error Rate by Service

```json
{
  "query": {
    "bool": {
      "must": [
        { "match": { "level": "WARNING" } },
        { "exists": { "field": "context.error_code" } }
      ]
    }
  },
  "aggs": {
    "by_service": {
      "terms": { "field": "context.resource_type.keyword" }
    }
  }
}
```

### 9.3 Trace a Request

```json
{
  "query": {
    "match": { "extra.request_id": "a1b2c3d4-1706300000" }
  },
  "sort": [{ "datetime": "asc" }]
}
```

---

## 10. Implementation Phases

### Phase 1: Request Correlation

| Task | Effort | Risk |
|------|--------|------|
| Create RequestIdMiddleware | 30 min | LOW |
| Create RequestIdProcessor | 30 min | LOW |
| Configure Monolog | 15 min | LOW |
| Test correlation | 30 min | LOW |

**Deliverable**: All logs have request_id

### Phase 2: Structured Logging

| Task | Effort | Risk |
|------|--------|------|
| Create LoggingTrait | 30 min | LOW |
| Update EmbeddedContentFetcher | 1 hour | LOW |
| Update PromiseResolver | 30 min | LOW |
| Update EditorialOrchestrator | 1 hour | LOW |

**Deliverable**: Consistent log structure

### Phase 3: Performance Logging

| Task | Effort | Risk |
|------|--------|------|
| Create Timer helper | 15 min | LOW |
| Add timing to Orchestrator | 30 min | LOW |
| Create business event logger | 30 min | LOW |

**Deliverable**: Performance visibility

### Phase 4: Dashboard

| Task | Effort | Risk |
|------|--------|------|
| Create ES index template | 30 min | LOW |
| Create Kibana visualizations | 2 hours | LOW |
| Create alerts | 1 hour | LOW |

**Deliverable**: Observability dashboard

---

## 11. Success Metrics

| Metric | Before | After |
|--------|--------|-------|
| Logs with request_id | 0% | 100% |
| Logs with resource_id | ~20% | 100% |
| Average debug time | ~30 min | ~5 min |
| Performance visibility | None | Full breakdown |
| Business event tracking | None | All key events |
