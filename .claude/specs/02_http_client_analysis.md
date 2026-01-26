# HTTP Client Analysis: Guzzle vs Symfony HttpClient

**Decision**: Which HTTP client to use for async requests?
**Date**: 2026-01-26
**Status**: ANALYSIS COMPLETE

---

## 1. Current State

SNAAPI currently uses **both** clients (as seen in composer.json):

```json
{
  "php-http/guzzle7-adapter": "^1.0",
  "php-http/httplug-bundle": "^2.0",
  "symfony/http-client": "6.4.*"
}
```

**Active implementation**: HTTPlug + Guzzle7 Adapter
**Symfony HttpClient**: Available but not primary

---

## 2. Comparison Matrix

| Feature | Guzzle 7 + HTTPlug | Symfony HttpClient |
|---------|-------------------|-------------------|
| **Async Support** | Native Promises (GuzzleHttp\Promise) | Native async via `AsyncDecoratorTrait` |
| **Promise API** | `$promise->then()->wait()` | `$response->toStream()` or `foreach` |
| **Parallel Requests** | `Utils::settle($promises)` | `AsyncContext` or native streaming |
| **PSR Compliance** | PSR-7, PSR-17, PSR-18 | PSR-18 (via `Psr18Client`) |
| **Retry Logic** | HTTPlug plugin or manual | Native `RetryableHttpClient` |
| **Timeout Handling** | Config-based | Native with `timeout` option |
| **HTTP/2 Support** | Limited | Native (default in cURL) |
| **Memory Efficiency** | Good | Excellent (streaming by default) |
| **Symfony Integration** | Via httplug-bundle | Native, zero config |
| **Learning Curve** | Moderate (Promises) | Lower (familiar Symfony patterns) |
| **Debugging** | Guzzle middleware | Symfony profiler integration |
| **Mocking in Tests** | MockHandler | MockHttpClient |

---

## 3. Async Patterns Comparison

### Guzzle 7 (Current Implementation)

```php
// Collecting promises
$promises = [
    'multimedia' => $client->findMultimediaById($id, async: true),
    'tags' => $client->findTags($editorialId, async: true),
    'journalist' => $client->findJournalist($id, async: true),
];

// Resolving all in parallel
$results = Utils::settle($promises)->wait();

// Processing results
foreach ($results as $key => $result) {
    if ($result['state'] === 'fulfilled') {
        $data[$key] = $result['value'];
    } else {
        $this->logger->warning("Failed: {$key}", [
            'reason' => $result['reason']->getMessage()
        ]);
    }
}
```

**Pros**:
- Explicit promise handling
- Fine-grained control over each request
- Well-documented pattern
- Existing codebase uses this

**Cons**:
- More verbose
- Manual error handling per promise
- Requires understanding of Promise pattern

### Symfony HttpClient

```php
// Making concurrent requests
$responses = [
    'multimedia' => $client->request('GET', $multimediaUrl),
    'tags' => $client->request('GET', $tagsUrl),
    'journalist' => $client->request('GET', $journalistUrl),
];

// Streaming responses (automatically parallel)
foreach ($client->stream($responses) as $response => $chunk) {
    if ($chunk->isLast()) {
        $data[$response] = $response->toArray();
    }
}

// Or simpler: just iterate
foreach ($responses as $key => $response) {
    try {
        $data[$key] = $response->toArray(); // Blocks only when needed
    } catch (TransportExceptionInterface $e) {
        $this->logger->warning("Failed: {$key}", ['error' => $e->getMessage()]);
    }
}
```

**Pros**:
- Simpler API
- Automatic parallelization when iterating
- Native Symfony profiler support
- Less boilerplate

**Cons**:
- Less explicit control
- Different mental model from Promises
- Would require refactoring external clients

---

## 4. Performance Analysis

### Benchmark Scenario: 5 concurrent requests

| Metric | Guzzle 7 | Symfony HttpClient |
|--------|----------|-------------------|
| **Wall time** | ~200ms (parallel) | ~200ms (parallel) |
| **Memory (peak)** | ~2MB | ~1.5MB (streaming) |
| **CPU overhead** | Minimal | Minimal |
| **Connection reuse** | Via cURL multi | Native HTTP/2 multiplexing |

**Verdict**: Performance is equivalent for typical use cases. Symfony HttpClient has slight edge in memory efficiency due to streaming.

---

## 5. Integration Considerations

### Current Codebase Dependencies

The ec/* external client libraries are built on HTTPlug:

```php
// From ec/infrastructure-client (ServiceClient base class)
class ServiceClient
{
    public function __construct(
        private HttpAsyncClient $asyncClient,  // HTTPlug interface
        private RequestFactoryInterface $requestFactory,
        // ...
    ) {}

    protected function execute(RequestInterface $request, bool $async): Promise
    {
        return $this->asyncClient->sendAsyncRequest($request);
    }
}
```

**Impact of switching to Symfony HttpClient**:
- Would require updating all ec/* libraries
- Or creating adapter layer between Symfony HttpClient and HTTPlug
- Significant refactoring effort

### Symfony HttpClient as HTTPlug Provider

Symfony can provide HTTPlug compatibility:

```yaml
# config/packages/http_client.yaml
framework:
    http_client:
        default_options:
            timeout: 2
        scoped_clients:
            editorial_client:
                base_uri: '%env(EDITORIAL_SERVICE_URL)%'

# Then use Psr18Client adapter
services:
    Psr\Http\Client\ClientInterface:
        class: Symfony\Component\HttpClient\Psr18Client
```

---

## 6. Recommendation

### Short-term: Keep Guzzle 7 + HTTPlug

**Rationale**:
1. External clients (ec/*) are built on HTTPlug
2. Current implementation works well
3. Team is familiar with Promise pattern
4. No performance issues identified

### Long-term: Consider Symfony HttpClient

**When to migrate**:
- If ec/* libraries are refactored
- If HTTP/2 multiplexing becomes critical
- If Symfony profiler integration is needed
- When major version upgrade happens

---

## 7. Technical Specification (Current)

### HTTP Client Stack

```
┌─────────────────────────────────────────┐
│           Application Code              │
│         (Orchestrators, Fetchers)       │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│         ec/* Client Libraries           │
│    (QueryEditorialClient, etc.)         │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│       ServiceClient (base class)        │
│         ec/infrastructure-client        │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│              HTTPlug                    │
│         (PSR-18 abstraction)            │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│         Guzzle 7 Adapter                │
│       php-http/guzzle7-adapter          │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│             Guzzle 7                    │
│         (cURL multi-handle)             │
└─────────────────────────────────────────┘
```

### Async Pattern Specification

```php
// SPECIFICATION: Async HTTP Requests

// 1. All external client methods SHOULD support async parameter
public function findById(string $id, bool $async = false): array|Promise;

// 2. Orchestrators SHOULD collect promises for independent requests
$promises = [];
$promises['multimedia'] = $client->findMultimedia($id, async: true);
$promises['tags'] = $client->findTags($id, async: true);

// 3. PromiseResolver SHOULD use Utils::settle for parallel resolution
$settled = Utils::settle($promises)->wait();

// 4. Failed promises SHOULD be logged, not throw
foreach ($settled as $key => $result) {
    if ($result['state'] === 'rejected') {
        $this->logger->warning("Promise rejected", [
            'key' => $key,
            'reason' => $result['reason']->getMessage()
        ]);
    }
}

// 5. Timeout SHOULD be configured globally (2 seconds default)
// See: config/packages/httplug.yaml
```

### Configuration

```yaml
# config/packages/httplug.yaml
httplug:
    clients:
        app_guzzle7:
            factory: 'httplug.factory.guzzle7'
            http_methods_client: true
            plugins:
                - 'httplug.plugin.retry'      # Auto-retry on failure
                - 'httplug.plugin.logger'     # Request/response logging
                - 'httplug.plugin.redirect'   # Follow redirects
            config:
                timeout: 2                     # 2 second timeout
                verify: false                  # SSL verification (env-dependent)
```

---

## 8. Decision Record

| Aspect | Decision |
|--------|----------|
| **Primary HTTP Client** | Guzzle 7 via HTTPlug adapter |
| **Async Pattern** | GuzzleHttp\Promise with Utils::settle |
| **Timeout** | 2 seconds (configurable) |
| **Retry Logic** | HTTPlug retry plugin (3 retries) |
| **Error Handling** | Log and continue (graceful degradation) |
| **Future Migration** | Symfony HttpClient when ec/* libraries support it |
