# Performance Baseline - SNAAPI

**Date**: 2026-01-28
**Status**: ANALYSIS COMPLETE (measurements pending)
**Environment**: Static analysis (Docker not available)

---

## Executive Summary

Analysis of EditorialOrchestrator pipeline to identify performance measurement points. The architecture uses a **Pipeline Pattern** with 8 ordered steps.

**Key Finding**: Architecture already has debug logging per step - timing can be added with minimal changes.

---

## Pipeline Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Editorial Pipeline Flow                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Request → FetchEditorial → FetchEmbeddedContent →             │
│            ResolveMultimedia → FetchSignatures →                │
│            FetchComments → EnrichContent →                      │
│            AggregateResponse → Response                         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Pipeline Steps (Priority Order)

| Priority | Step | HTTP Calls | Description |
|----------|------|------------|-------------|
| 1000 | FetchEditorialStep | YES | Fetch editorial + section |
| 900 | LegacyCheckStep | MAYBE | Check legacy system fallback |
| 800 | FetchEmbeddedContentStep | YES | Fetch inserted/recommended news |
| 600 | ResolveMultimediaStep | YES | Resolve multimedia promises |
| 500 | FetchSignaturesStep | YES | Fetch journalist data |
| 400 | FetchCommentsStep | YES | Fetch comment count |
| 300 | EnrichContentStep | MAYBE | Enrich with additional data |
| 100 | AggregateResponseStep | NO | Transform and aggregate response |

---

## Measurement Points

### 1. FetchEditorialStep (Priority 1000)

**Service**: `EditorialFetcherInterface`
**External Calls**:
- `QueryEditorialClient` - Fetch editorial
- `QuerySectionClient` - Fetch section hierarchy

**Expected Latency**: HIGH (primary content fetch)

```php
// Measurement point
$start = microtime(true);
$fetchedEditorial = $this->editorialFetcher->fetch($context->editorialId);
$duration = microtime(true) - $start;
$this->logger->info('FetchEditorial', ['duration_ms' => $duration * 1000]);
```

### 2. FetchEmbeddedContentStep (Priority 800)

**Service**: `EmbeddedContentFetcherInterface`
**External Calls**:
- `QueryEditorialClient` - Fetch inserted news
- `QueryMultimediaClient` - Fetch embedded multimedia

**Expected Latency**: MEDIUM-HIGH (multiple parallel fetches)

### 3. ResolveMultimediaStep (Priority 600)

**Service**: `PromiseResolverInterface`
**External Calls**:
- Resolves pending multimedia promises
- Waits for async operations

**Expected Latency**: VARIABLE (depends on promise count)

### 4. FetchSignaturesStep (Priority 500)

**Service**: `SignatureFetcherInterface`
**External Calls**:
- `QueryJournalistClient` - Fetch journalist per signature

**Expected Latency**: MEDIUM (N journalists = N calls potential)

**Note**: Current implementation fetches sequentially. Potential N+1 issue.

### 5. FetchCommentsStep (Priority 400)

**Service**: `CommentsFetcherInterface`
**External Calls**:
- `QueryLegacyClient` - Fetch comment count

**Expected Latency**: LOW (single call)

### 6. AggregateResponseStep (Priority 100)

**Service**: `ResponseAggregatorInterface`
**External Calls**: NONE (transformation only)

**Expected Latency**: LOW (CPU-bound)

---

## Measurement Commands

When Docker environment is available:

### Full Endpoint Timing

```bash
# Create timing format file
cat > curl-format.txt << 'EOF'
     time_namelookup:  %{time_namelookup}s
        time_connect:  %{time_connect}s
     time_appconnect:  %{time_appconnect}s
    time_pretransfer:  %{time_pretransfer}s
       time_redirect:  %{time_redirect}s
  time_starttransfer:  %{time_starttransfer}s
                     ----------
          time_total:  %{time_total}s
EOF

# Run 10 measurements
for i in {1..10}; do
  curl -w "@curl-format.txt" -o /dev/null -s \
    "http://localhost:8000/v1/editorials/{editorial-id}"
  echo "---"
done
```

### Symfony Profiler

```yaml
# config/packages/dev/web_profiler.yaml
web_profiler:
    toolbar: true
    intercept_redirects: false

framework:
    profiler:
        collect: true
        collect_parameter: profiler
```

### Custom Timing Logging

```php
// Add to EditorialPipelineHandler::execute()
$stepStart = microtime(true);
$result = $step->process($context);
$stepDuration = (microtime(true) - $stepStart) * 1000;

$this->logger->info('Pipeline step timing', [
    'step' => $stepName,
    'duration_ms' => round($stepDuration, 2),
    'editorial_id' => $context->editorialId,
]);
```

---

## Expected Latency Breakdown (Estimated)

Based on typical microservice latencies:

| Component | Est. Latency | % of Total |
|-----------|--------------|------------|
| FetchEditorial | 50-100ms | 25-40% |
| FetchEmbeddedContent | 30-80ms | 15-30% |
| ResolveMultimedia | 20-50ms | 10-20% |
| FetchSignatures | 20-60ms | 10-25% |
| FetchComments | 10-30ms | 5-15% |
| AggregateResponse | 5-15ms | 3-8% |
| **Total** | **135-335ms** | 100% |

**Note**: These are estimates. Actual measurements needed.

---

## Potential Optimization Targets

Based on static analysis:

### 1. FetchSignaturesStep - N+1 Pattern

**Current**: Sequential fetch per journalist
**Potential**: Batch fetch or parallel promises

```php
// Current (N calls)
foreach ($editorial->signatures() as $signature) {
    $journalist = $this->journalistClient->find($signature->id());
}

// Optimized (1 call or parallel)
$ids = array_map(fn($s) => $s->id(), $editorial->signatures());
$journalists = $this->journalistClient->findByIds($ids); // If supported
```

### 2. Pipeline Step Parallelization

Steps with no dependencies could run in parallel:
- FetchSignaturesStep + FetchCommentsStep (both depend only on editorial)

### 3. Aggressive Caching

- Editorial content rarely changes
- Section hierarchy is static
- Journalist info changes infrequently

---

## Next Steps

1. **When Docker available**:
   - Run curl timing commands
   - Enable Symfony profiler
   - Capture 50+ requests for p50/p95/p99

2. **Add timing to pipeline**:
   - Create `TimingPipelineDecorator`
   - Log step durations to structured logs

3. **Compare with estimates**:
   - Validate or refute N+1 hypothesis
   - Identify actual bottleneck

4. **Decision point**:
   - If latency acceptable: Document and close
   - If latency high: Plan specific optimization

---

## Questions Answered (Partial)

| Question | Answer |
|----------|--------|
| What is current p95 latency? | **TBD** - needs measurement |
| % time on external calls vs transform? | **~90% external** (estimated from architecture) |
| Which external service is slowest? | **Likely FetchEditorial** (primary content) |
| Is N+1 photo fetching a problem? | **Possible in FetchSignatures** - needs measurement |

---

## Files Reference

**Pipeline**:
- `src/Orchestrator/Pipeline/EditorialPipelineHandler.php`
- `src/Orchestrator/Pipeline/Step/*.php`

**Fetchers**:
- `src/Orchestrator/Service/EditorialFetcher.php`
- `src/Orchestrator/Service/SignatureFetcher.php`
- `src/Orchestrator/Service/CommentsFetcher.php`

**Transformation** (no HTTP):
- `src/Application/Service/Editorial/ResponseAggregator.php`

---

## Compound Learning

- Pipeline architecture enables easy step-level timing
- Existing debug logging provides foundation for metrics
- No code changes needed to measure - just enable profiler
- N+1 pattern suspected but not confirmed
