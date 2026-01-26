# Error Handling Improvement Plan

**Project**: SNAAPI
**Date**: 2026-01-26
**Status**: PLAN

---

## 1. Current State Analysis

### What's GOOD

| Component | Status |
|-----------|--------|
| Domain exceptions hierarchy | ✅ Well structured |
| ExceptionSubscriber centralized | ✅ Excellent |
| HTTP status codes mapping | ✅ Correct |
| Graceful degradation | ✅ Services continue on partial failures |

### What NEEDS IMPROVEMENT

| Issue | Location | Impact |
|-------|----------|--------|
| Inconsistent log levels | EmbeddedContentFetcher | Hard to filter logs |
| Logs without context | Multiple services | Can't trace failures |
| Silent catches | PromiseResolver | Failures hidden |
| No error codes in services | Orchestrators | Can't categorize errors |

---

## 2. Current Error Handling Patterns

### Pattern A: ExceptionSubscriber (GOOD)

```php
// ✅ GOOD: Structured, contextual, differentiated
private function logException(\Throwable $throwable): void
{
    if ($throwable instanceof DomainExceptionInterface) {
        $this->logger->info('Domain exception', [
            'code' => $throwable->getErrorCode(),
            'message' => $throwable->getMessage(),
        ]);
    } else {
        $this->logger->error('Unexpected exception', [
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
            'trace' => $throwable->getTraceAsString(),
        ]);
    }
}
```

### Pattern B: Services (BAD)

```php
// ❌ BAD: No context, inconsistent levels
} catch (\Throwable $e) {
    $this->logger->error($e->getMessage());  // Just message!
    return null;
}

// ❌ BAD: Different level, same situation
} catch (\Throwable $e) {
    $this->logger->warning($e->getMessage());  // Inconsistent!
    return [];
}
```

### Pattern C: PromiseResolver (SILENT)

```php
// ❌ BAD: Silent catch, no logging
try {
    $result = $promise->wait();
} catch (\Throwable) {
    return [];  // Silently fails!
}
```

---

## 3. Proposed Error Handling Standards

### 3.1 Logging Levels

| Level | When to Use | Example |
|-------|-------------|---------|
| `ERROR` | Unexpected failures that need investigation | Database connection failed |
| `WARNING` | Expected failures in graceful degradation | Photo not found, using fallback |
| `INFO` | Domain exceptions (expected business cases) | Editorial not published |
| `DEBUG` | Detailed tracing (dev only) | Cache hit/miss |

### 3.2 Required Context Fields

```php
// STANDARD: All error logs MUST include:
$this->logger->warning('Failed to fetch resource', [
    'error_code' => 'PHOTO_FETCH_FAILED',      // Categorizable
    'resource_type' => 'photo',                 // What failed
    'resource_id' => $photoId,                  // Which one
    'exception_class' => $e::class,             // Exception type
    'exception_message' => $e->getMessage(),    // Original message
    'context' => [                              // Business context
        'editorial_id' => $editorialId,
        'operation' => 'body_tag_resolution',
    ],
]);
```

### 3.3 Error Code Constants

```php
// src/Infrastructure/Error/ErrorCode.php
namespace App\Infrastructure\Error;

final class ErrorCode
{
    // External Service Errors (1xxx)
    public const EDITORIAL_FETCH_FAILED = 'ERR_1001';
    public const SECTION_FETCH_FAILED = 'ERR_1002';
    public const MULTIMEDIA_FETCH_FAILED = 'ERR_1003';
    public const PHOTO_FETCH_FAILED = 'ERR_1004';
    public const TAG_FETCH_FAILED = 'ERR_1005';
    public const JOURNALIST_FETCH_FAILED = 'ERR_1006';
    public const MEMBERSHIP_FETCH_FAILED = 'ERR_1007';

    // Promise Errors (2xxx)
    public const PROMISE_REJECTED = 'ERR_2001';
    public const PROMISE_TIMEOUT = 'ERR_2002';

    // Transform Errors (3xxx)
    public const TRANSFORM_FAILED = 'ERR_3001';
    public const MISSING_REQUIRED_DATA = 'ERR_3002';

    // Validation Errors (4xxx)
    public const INVALID_EDITORIAL_ID = 'ERR_4001';
    public const INVALID_REQUEST = 'ERR_4002';
}
```

---

## 4. Refactored Error Handling

### 4.1 EmbeddedContentFetcher (BEFORE → AFTER)

**BEFORE**:
```php
try {
    $embeddedEditorial = $this->editorialClient->findEditorialById($editorialId);
} catch (\Throwable $e) {
    $this->logger->error($e->getMessage());
    return null;
}
```

**AFTER**:
```php
try {
    $embeddedEditorial = $this->editorialClient->findEditorialById($editorialId);
} catch (\Throwable $e) {
    $this->logger->warning('Failed to fetch embedded editorial', [
        'error_code' => ErrorCode::EDITORIAL_FETCH_FAILED,
        'resource_type' => 'editorial',
        'resource_id' => $editorialId,
        'exception_class' => $e::class,
        'exception_message' => $e->getMessage(),
        'context' => [
            'parent_editorial_id' => $this->currentEditorialId,
            'operation' => 'fetch_inserted_news',
        ],
    ]);
    return null;  // Graceful degradation
}
```

### 4.2 PromiseResolver (BEFORE → AFTER)

**BEFORE**:
```php
try {
    $result = $promise->wait();
} catch (\Throwable) {
    return [];  // Silent!
}
```

**AFTER**:
```php
try {
    $result = $promise->wait();
} catch (\Throwable $e) {
    $this->logger->warning('Membership promise failed', [
        'error_code' => ErrorCode::PROMISE_REJECTED,
        'resource_type' => 'membership_links',
        'exception_class' => $e::class,
        'exception_message' => $e->getMessage(),
    ]);
    return [];  // Graceful degradation with logging
}
```

### 4.3 EditorialOrchestrator Photo Fetch (BEFORE → AFTER)

**BEFORE**:
```php
try {
    $photo = $this->queryMultimediaClient->findPhotoById($id);
    $result[$id] = $photo;
} catch (\Throwable $throwable) {
    $this->logger->error('Failed to fetch photo: ' . $throwable->getMessage());
}
```

**AFTER**:
```php
try {
    $photo = $this->queryMultimediaClient->findPhotoById($id);
    $result[$id] = $photo;
} catch (\Throwable $e) {
    $this->logger->warning('Failed to fetch body tag photo', [
        'error_code' => ErrorCode::PHOTO_FETCH_FAILED,
        'resource_type' => 'photo',
        'resource_id' => $id,
        'exception_class' => $e::class,
        'exception_message' => $e->getMessage(),
        'context' => [
            'editorial_id' => $this->currentEditorialId,
            'operation' => 'retrieve_photos_from_body_tags',
        ],
    ]);
    // Continue without this photo (graceful degradation)
}
```

---

## 5. Error Handling Service

### 5.1 ErrorLogger Service

```php
// src/Infrastructure/Error/ErrorLogger.php
namespace App\Infrastructure\Error;

use Psr\Log\LoggerInterface;

final class ErrorLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Log a graceful degradation (operation continues despite failure)
     */
    public function logGracefulDegradation(
        string $errorCode,
        string $resourceType,
        ?string $resourceId,
        \Throwable $exception,
        array $context = [],
    ): void {
        $this->logger->warning('Graceful degradation: operation continued despite failure', [
            'error_code' => $errorCode,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'context' => $context,
        ]);
    }

    /**
     * Log a critical failure (operation cannot continue)
     */
    public function logCriticalFailure(
        string $errorCode,
        string $resourceType,
        ?string $resourceId,
        \Throwable $exception,
        array $context = [],
    ): void {
        $this->logger->error('Critical failure: operation aborted', [
            'error_code' => $errorCode,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_trace' => $exception->getTraceAsString(),
            'context' => $context,
        ]);
    }

    /**
     * Log a promise rejection
     */
    public function logPromiseRejection(
        string $resourceType,
        ?string $resourceId,
        mixed $reason,
        array $context = [],
    ): void {
        $message = $reason instanceof \Throwable
            ? $reason->getMessage()
            : (string) $reason;

        $this->logger->warning('Promise rejected', [
            'error_code' => ErrorCode::PROMISE_REJECTED,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'rejection_reason' => $message,
            'context' => $context,
        ]);
    }
}
```

### 5.2 Usage in Services

```php
// EmbeddedContentFetcher with ErrorLogger
final class EmbeddedContentFetcher
{
    public function __construct(
        private readonly ErrorLogger $errorLogger,
        // ... other dependencies
    ) {}

    private function fetchEmbeddedEditorial(string $editorialId): ?array
    {
        try {
            $embeddedEditorial = $this->editorialClient->findEditorialById($editorialId);
            // ... rest of logic
        } catch (\Throwable $e) {
            $this->errorLogger->logGracefulDegradation(
                errorCode: ErrorCode::EDITORIAL_FETCH_FAILED,
                resourceType: 'editorial',
                resourceId: $editorialId,
                exception: $e,
                context: ['operation' => 'fetch_embedded_editorial'],
            );
            return null;
        }
    }
}
```

---

## 6. Promise Error Handling

### 6.1 Enhanced PromiseResolver

```php
final class PromiseResolver implements PromiseResolverInterface
{
    public function __construct(
        private readonly ErrorLogger $errorLogger,
    ) {}

    private function extractFulfilledMultimedia(array $settled): array
    {
        $result = [];

        foreach ($settled as $key => $promise) {
            if (self::PROMISE_STATE_FULFILLED === $promise['state']) {
                $multimedia = $promise['value'];
                $result[$multimedia->id()] = $multimedia;
            } else {
                // ✅ Proper logging with context
                $this->errorLogger->logPromiseRejection(
                    resourceType: 'multimedia',
                    resourceId: $key,
                    reason: $promise['reason'],
                    context: ['operation' => 'resolve_multimedia_promises'],
                );
            }
        }

        return $result;
    }

    public function resolveMembershipLinks(
        Promise|PromiseInterface|null $promise,
        array $links,
    ): array {
        if (null === $promise) {
            return [];
        }

        try {
            $result = $promise->wait();
        } catch (\Throwable $e) {
            // ✅ No longer silent!
            $this->errorLogger->logGracefulDegradation(
                errorCode: ErrorCode::MEMBERSHIP_FETCH_FAILED,
                resourceType: 'membership_links',
                resourceId: null,
                exception: $e,
                context: ['links_count' => count($links)],
            );
            return [];
        }

        if (empty($result)) {
            return [];
        }

        return array_combine($links, $result);
    }
}
```

---

## 7. Circuit Breaker Pattern (Future)

### For External Services

```php
// src/Infrastructure/CircuitBreaker/CircuitBreaker.php
namespace App\Infrastructure\CircuitBreaker;

final class CircuitBreaker
{
    private const FAILURE_THRESHOLD = 5;
    private const RECOVERY_TIMEOUT = 30;  // seconds

    private int $failures = 0;
    private ?int $lastFailure = null;
    private string $state = 'CLOSED';  // CLOSED, OPEN, HALF_OPEN

    public function isAvailable(): bool
    {
        if ($this->state === 'CLOSED') {
            return true;
        }

        if ($this->state === 'OPEN') {
            // Check if recovery timeout has passed
            if (time() - $this->lastFailure > self::RECOVERY_TIMEOUT) {
                $this->state = 'HALF_OPEN';
                return true;
            }
            return false;
        }

        // HALF_OPEN: allow one request to test
        return true;
    }

    public function recordSuccess(): void
    {
        $this->failures = 0;
        $this->state = 'CLOSED';
    }

    public function recordFailure(): void
    {
        $this->failures++;
        $this->lastFailure = time();

        if ($this->failures >= self::FAILURE_THRESHOLD) {
            $this->state = 'OPEN';
        }
    }
}
```

### Usage

```php
// In EmbeddedContentFetcher
if (!$this->editorialCircuitBreaker->isAvailable()) {
    $this->errorLogger->logGracefulDegradation(
        errorCode: 'CIRCUIT_OPEN',
        resourceType: 'editorial',
        resourceId: $editorialId,
        exception: new CircuitOpenException(),
        context: ['circuit' => 'editorial_service'],
    );
    return null;
}

try {
    $result = $this->editorialClient->findEditorialById($editorialId);
    $this->editorialCircuitBreaker->recordSuccess();
    return $result;
} catch (\Throwable $e) {
    $this->editorialCircuitBreaker->recordFailure();
    throw $e;
}
```

---

## 8. Implementation Phases

### Phase 1: Standardize Logging

| Task | Files | Risk |
|------|-------|------|
| Create ErrorCode constants | 1 new file | LOW |
| Create ErrorLogger service | 1 new file | LOW |
| Update EmbeddedContentFetcher | 1 file | LOW |
| Update PromiseResolver | 1 file | LOW |
| Update EditorialOrchestrator | 1 file | LOW |

**Deliverable**: Consistent, contextual error logging

### Phase 2: Standardize Log Levels

| Task | Files | Risk |
|------|-------|------|
| Audit all catch blocks | ~10 files | LOW |
| Apply level standards | ~10 files | LOW |
| Update tests | ~5 files | LOW |

**Deliverable**: Consistent log levels across codebase

### Phase 3: Circuit Breaker (Optional)

| Task | Files | Risk |
|------|-------|------|
| Create CircuitBreaker class | 1 new file | LOW |
| Integrate with clients | ~5 files | MEDIUM |
| Add monitoring | 1 file | LOW |

**Deliverable**: Resilience to cascading failures

---

## 9. Error Response Format

### Current (GOOD)

```json
{
  "errors": [
    {
      "code": "EDITORIAL_NOT_FOUND",
      "message": "Editorial with ID 123 was not found"
    }
  ]
}
```

### Keep This Format

The current error response format in ExceptionSubscriber is good. No changes needed.

---

## 10. Monitoring Dashboard Queries

### Kibana/Elasticsearch Queries

```json
// Graceful degradations by error code
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
    "by_error_code": {
      "terms": { "field": "context.error_code.keyword" }
    }
  }
}

// Critical failures
{
  "query": {
    "bool": {
      "must": [
        { "match": { "level": "ERROR" } },
        { "match": { "message": "Critical failure" } }
      ]
    }
  }
}

// Promise rejections by resource type
{
  "query": {
    "match": { "context.error_code": "ERR_2001" }
  },
  "aggs": {
    "by_resource": {
      "terms": { "field": "context.resource_type.keyword" }
    }
  }
}
```

---

## 11. Success Metrics

| Metric | Before | After |
|--------|--------|-------|
| Logs with error_code | 0% | 100% |
| Logs with resource_id | ~20% | 100% |
| Silent catches | 3 places | 0 |
| Inconsistent log levels | 5+ places | 0 |
| Time to diagnose issue | ~30 min | ~5 min |
