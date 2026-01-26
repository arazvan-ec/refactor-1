# Cache Strategy Plan - HTTPlug 1 Minute TTL

**Project**: SNAAPI
**Date**: 2026-01-26
**Status**: PLAN

---

## 1. Current State Analysis

### What EXISTS

| Component | Status | Details |
|-----------|--------|---------|
| HTTP Response Cache Headers | ✅ | `s-maxage=7200, max-age=60` |
| Cache Purge Events | ✅ | AMQP events trigger CDN purge |
| HTTPlug Logger Plugin | ✅ | Logs all HTTP requests |
| HTTPlug Retry Plugin | ✅ | 3 retries configured |

### What's MISSING

| Component | Status | Impact |
|-----------|--------|--------|
| HTTPlug Cache Plugin | ❌ | All requests to ec/* hit network |
| Redis/APCu Cache | ❌ | No application-level cache |
| Client-level Cache | ❌ | Each request = HTTP call |

### Current Request Flow (NO CACHE)

```
SNAAPI Request
    │
    ├── QueryEditorialClient.findById()     → HTTP to editorial-service
    ├── QuerySectionClient.findById()       → HTTP to section-service
    ├── QueryMultimediaClient.findById() x5 → 5x HTTP to multimedia-service
    ├── QueryTagClient.findById() x3        → 3x HTTP to tag-service
    └── QueryJournalistClient.findById() x2 → 2x HTTP to journalist-service

TOTAL: ~12 HTTP calls per request (NO CACHE)
```

---

## 2. Proposed Cache Architecture

### Target: HTTPlug Cache Plugin with 1 Minute TTL

```
SNAAPI Request
    │
    └── HTTPlug Client
            │
            ├── Cache Plugin (NEW)
            │       │
            │       ├── Cache HIT? → Return cached response
            │       │
            │       └── Cache MISS? → HTTP call → Store in cache (60s TTL)
            │
            └── Retry Plugin → Logger Plugin → Network
```

### Cache Layers

```
┌─────────────────────────────────────────────────────────────┐
│                    CDN (Cloudflare/Varnish)                 │
│                    TTL: 2 hours (s-maxage)                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    SNAAPI Application                        │
│  ┌───────────────────────────────────────────────────────┐  │
│  │              HTTPlug Cache Plugin (NEW)               │  │
│  │              TTL: 1 minute                            │  │
│  │              Storage: Redis (recommended)             │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              External Microservices (ec/*)                  │
│        editorial-service, multimedia-service, etc.          │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Implementation Plan

### 3.1 Install Required Packages

```bash
composer require php-http/cache-plugin
composer require symfony/cache  # If not already installed
```

### 3.2 Configure Redis Cache Pool

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            httplug.cache:
                adapter: cache.adapter.redis
                default_lifetime: 60  # 1 minute default
                provider: 'redis://localhost:6379'
```

### 3.3 Configure HTTPlug Cache Plugin

```yaml
# config/packages/httplug.yaml
httplug:
    plugins:
        cache:
            cache_pool: 'httplug.cache'
            config:
                default_ttl: 60                    # 1 minute
                respect_response_cache_directives:
                    - no-cache
                    - no-store
                    - max-age
                cache_key_generator: null          # Use default

        retry:
            retry: '%env(int:SERVICE_HTTP_RETRIES)%'
        logger: ~

    clients:
        app_guzzle7:
            factory: 'httplug.factory.guzzle7'
            http_methods_client: true
            plugins:
                - 'httplug.plugin.cache'           # ADD: Cache first!
                - 'httplug.plugin.content_length'
                - 'httplug.plugin.redirect'
                - 'httplug.plugin.logger'
                - 'httplug.plugin.retry'
            config:
                timeout: 2
                verify: false
```

**Plugin Order Matters**:
```
1. cache     → Check cache first (avoid network if hit)
2. content_length → Add header
3. redirect  → Follow redirects
4. logger    → Log request/response
5. retry     → Retry on failure
```

### 3.4 Alternative: APCu for Single-Server

```yaml
# config/packages/cache.yaml (if no Redis available)
framework:
    cache:
        pools:
            httplug.cache:
                adapter: cache.adapter.apcu
                default_lifetime: 60
```

**APCu Pros**: No external dependency, very fast
**APCu Cons**: Not shared between servers, lost on restart

---

## 4. Cache Key Strategy

### Default Key Generation

HTTPlug cache plugin generates keys based on:
- HTTP Method (GET only cached by default)
- Full URL (including query params)
- Vary headers

```php
// Example cache keys:
// GET https://editorial.ec/v1/editorials/123
// Key: "httplug_cache_GET_editorial.ec_v1_editorials_123"

// GET https://multimedia.ec/v1/photos/456
// Key: "httplug_cache_GET_multimedia.ec_v1_photos_456"
```

### Custom Key Generator (Optional)

```php
// src/Infrastructure/Cache/SnapiCacheKeyGenerator.php
namespace App\Infrastructure\Cache;

use Http\Client\Common\Plugin\Cache\Generator\CacheKeyGenerator;
use Psr\Http\Message\RequestInterface;

final class SnapiCacheKeyGenerator implements CacheKeyGenerator
{
    public function generate(RequestInterface $request): string
    {
        $method = $request->getMethod();
        $uri = $request->getUri();

        // Normalize key: remove trailing slashes, lowercase host
        $key = sprintf(
            'snaapi_%s_%s_%s',
            strtolower($method),
            strtolower($uri->getHost()),
            ltrim($uri->getPath(), '/')
        );

        // Include query params if present
        if ($uri->getQuery()) {
            $key .= '_' . md5($uri->getQuery());
        }

        return $key;
    }
}
```

```yaml
# config/packages/httplug.yaml
httplug:
    plugins:
        cache:
            cache_pool: 'httplug.cache'
            config:
                default_ttl: 60
                cache_key_generator: App\Infrastructure\Cache\SnapiCacheKeyGenerator
```

---

## 5. Cache Invalidation Strategy

### Option A: TTL-Based Only (Recommended for Start)

```
┌─────────────────────────────────────────┐
│  Editorial updated in CMS               │
│           │                             │
│           ▼                             │
│  CDN Purge (existing AMQP handler)      │
│           │                             │
│           ▼                             │
│  HTTPlug cache expires naturally (60s)  │
│           │                             │
│           ▼                             │
│  Next request fetches fresh data        │
└─────────────────────────────────────────┘

Max staleness: 60 seconds
```

**Pros**: Simple, no additional infrastructure
**Cons**: Up to 60s stale data after update

### Option B: Event-Based Invalidation (Future)

```php
// src/EventHandler/InvalidateHttpCacheHandler.php
namespace App\EventHandler;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class InvalidateHttpCacheHandler
{
    public function __construct(
        private readonly CacheItemPoolInterface $httplugCache,
    ) {}

    public function __invoke(EditorialUpdatedEvent $event): void
    {
        $editorialId = $event->editorialId();

        // Invalidate specific cache keys
        $keys = [
            "snaapi_get_editorial.ec_v1_editorials_{$editorialId}",
            // Add related keys...
        ];

        $this->httplugCache->deleteItems($keys);
    }
}
```

**Pros**: Immediate invalidation
**Cons**: More complex, need to track all related keys

---

## 6. TTL Configuration per Service

### Different TTLs for Different Data

```php
// src/Infrastructure/Cache/ServiceTtlCalculator.php
namespace App\Infrastructure\Cache;

final class ServiceTtlCalculator
{
    private const TTLS = [
        'editorial.ec' => 60,      // 1 minute - content changes often
        'section.ec' => 300,       // 5 minutes - sections rarely change
        'multimedia.ec' => 120,    // 2 minutes - media metadata stable
        'tag.ec' => 300,           // 5 minutes - tags rarely change
        'journalist.ec' => 600,    // 10 minutes - author info very stable
        'membership.ec' => 60,     // 1 minute - promotional links change
    ];

    public function getTtl(string $host): int
    {
        return self::TTLS[$host] ?? 60;
    }
}
```

### Custom Cache Listener (Advanced)

```php
// src/Infrastructure/Cache/DynamicTtlCacheListener.php
namespace App\Infrastructure\Cache;

use Http\Client\Common\Plugin\Cache\Listener\CacheListener;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class DynamicTtlCacheListener implements CacheListener
{
    public function __construct(
        private readonly ServiceTtlCalculator $ttlCalculator,
    ) {}

    public function onCacheResponse(
        RequestInterface $request,
        ResponseInterface $response,
        bool $fromCache,
        ?int $cacheAge
    ): ResponseInterface {
        // Log cache hit/miss for observability
        return $response;
    }

    public function onCacheMiss(RequestInterface $request): void
    {
        // Could be used for metrics
    }
}
```

---

## 7. Monitoring & Observability

### Cache Hit/Miss Metrics

```yaml
# config/packages/httplug.yaml
httplug:
    plugins:
        cache:
            cache_pool: 'httplug.cache'
            config:
                default_ttl: 60
                # Enable cache headers for debugging
                cache_listeners:
                    - App\Infrastructure\Cache\CacheMetricsListener
```

```php
// src/Infrastructure/Cache/CacheMetricsListener.php
namespace App\Infrastructure\Cache;

use Http\Client\Common\Plugin\Cache\Listener\CacheListener;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class CacheMetricsListener implements CacheListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function onCacheResponse(
        RequestInterface $request,
        ResponseInterface $response,
        bool $fromCache,
        ?int $cacheAge
    ): ResponseInterface {
        $this->logger->info('HTTPlug cache', [
            'url' => (string) $request->getUri(),
            'cache_hit' => $fromCache,
            'cache_age' => $cacheAge,
            'method' => $request->getMethod(),
        ]);

        return $response;
    }
}
```

### Expected Metrics

| Metric | Target |
|--------|--------|
| Cache Hit Rate | > 70% |
| Average Response Time (hit) | < 5ms |
| Average Response Time (miss) | ~50ms |
| Memory Usage (Redis) | < 100MB |

---

## 8. Implementation Phases

### Phase 1: Basic Cache Setup

| Task | Effort | Risk |
|------|--------|------|
| Install php-http/cache-plugin | 5 min | LOW |
| Configure Redis cache pool | 15 min | LOW |
| Add cache plugin to HTTPlug | 10 min | LOW |
| Test in development | 30 min | LOW |

**Deliverable**: Cache working with 60s TTL

### Phase 2: Observability

| Task | Effort | Risk |
|------|--------|------|
| Add CacheMetricsListener | 30 min | LOW |
| Add structured logging | 15 min | LOW |
| Create dashboard (Kibana/Grafana) | 2 hours | LOW |

**Deliverable**: Cache hit/miss visibility

### Phase 3: Tuning (Optional)

| Task | Effort | Risk |
|------|--------|------|
| Custom TTLs per service | 1 hour | LOW |
| Custom key generator | 1 hour | LOW |
| Event-based invalidation | 2 hours | MEDIUM |

**Deliverable**: Optimized cache strategy

---

## 9. Configuration Summary

### Final httplug.yaml

```yaml
httplug:
    plugins:
        cache:
            cache_pool: 'httplug.cache'
            config:
                default_ttl: 60
                respect_response_cache_directives:
                    - no-cache
                    - no-store
                    - max-age
                cache_listeners:
                    - App\Infrastructure\Cache\CacheMetricsListener

        retry:
            retry: '%env(int:SERVICE_HTTP_RETRIES)%'

        logger: ~

    clients:
        app_guzzle7:
            factory: 'httplug.factory.guzzle7'
            http_methods_client: true
            plugins:
                - 'httplug.plugin.cache'
                - 'httplug.plugin.content_length'
                - 'httplug.plugin.redirect'
                - 'httplug.plugin.logger'
                - 'httplug.plugin.retry'
            config:
                timeout: 2
                verify: false
```

### Final cache.yaml

```yaml
framework:
    cache:
        pools:
            httplug.cache:
                adapter: cache.adapter.redis
                default_lifetime: 60
                provider: '%env(REDIS_URL)%'
```

### Environment Variables

```bash
# .env
REDIS_URL=redis://localhost:6379
SERVICE_HTTP_RETRIES=3
```

---

## 10. Performance Impact

### Before (No Cache)

```
Request with 12 HTTP calls:
- 12 × 50ms average = 600ms network time
- Total request: ~700ms
```

### After (With Cache, 70% hit rate)

```
Request with 12 HTTP calls:
- 4 cache misses × 50ms = 200ms network
- 8 cache hits × 2ms = 16ms
- Total request: ~300ms

Improvement: ~57% faster
```

---

## 11. Rollback Plan

If issues arise:

```yaml
# Simply remove cache plugin from client
httplug:
    clients:
        app_guzzle7:
            plugins:
                # - 'httplug.plugin.cache'  # Commented out
                - 'httplug.plugin.content_length'
                - 'httplug.plugin.redirect'
                - 'httplug.plugin.logger'
                - 'httplug.plugin.retry'
```

No code changes required, just configuration.
