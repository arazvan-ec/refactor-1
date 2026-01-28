# Feature: Performance Baseline

## Overview

**Feature ID**: performance-baseline
**Created**: 2026-01-28
**Trust Level**: LOW CONTROL (measurement only, no code changes)
**Estimated Time**: 2 hours

---

## Objective

Establish performance baseline metrics for SNAAPI API endpoints to enable data-driven optimization decisions.

---

## Context

### Why Now

From compound log:
> "Spec 06 optimiza N+1 photo fetching sin medir latencia real. No hay datos que justifiquen la complejidad."

**Rule applied**: Measure before optimize.

### Current State

- No documented latency metrics (p50, p95, p99)
- No baseline for comparison after optimizations
- Optimization proposals (async photo batching) lack data justification

### Success Definition

After this feature:
- We have documented baseline metrics
- We can make data-driven decisions about optimizations
- Future PRs can compare against baseline

---

## Acceptance Criteria

- [ ] Document current API response times (p50, p95, p99)
- [ ] Identify slowest endpoints/operations
- [ ] Create metrics collection approach (manual or automated)
- [ ] Document findings in `.claude/analysis/performance_baseline.md`
- [ ] Update compound log with learnings

---

## Scope

### In Scope

1. Measure `/v1/editorials/{id}` endpoint latency
2. Identify slowest components (fetch, transform, serialize)
3. Document external service call times
4. Create baseline report

### Out of Scope

- Performance optimizations (separate feature)
- Infrastructure changes
- Caching strategy changes
- Code modifications

---

## Approach

### Option A: Manual Measurement (Recommended)

```bash
# Using curl with timing
curl -w "@curl-format.txt" -o /dev/null -s "http://localhost/v1/editorials/123"

# curl-format.txt contents:
#   time_namelookup:  %{time_namelookup}\n
#   time_connect:     %{time_connect}\n
#   time_appconnect:  %{time_appconnect}\n
#   time_pretransfer: %{time_pretransfer}\n
#   time_redirect:    %{time_redirect}\n
#   time_starttransfer: %{time_starttransfer}\n
#   time_total:       %{time_total}\n
```

### Option B: Symfony Profiler

- Enable profiler in dev environment
- Analyze timeline for each request
- Document external call durations

### Option C: Application Logging

- Add timing logs to orchestrators
- Measure fetch vs transform phases
- Aggregate over sample requests

---

## Deliverables

1. `performance_baseline.md` - Full analysis report
2. Updated compound log with metrics patterns
3. Recommendations for future optimization priorities

---

## Questions to Answer

1. What is current p95 latency for editorial endpoint?
2. What percentage of time is spent on external calls vs transformation?
3. Which external service is slowest?
4. Is N+1 photo fetching actually a problem?

---

## References

- Compound log: `.claude/project/compound_log.md`
- Async analysis: `.claude/features/snaapi-improvements-v2/03_async_analysis.md`
- EditorialOrchestrator: `src/Orchestrator/Chain/EditorialOrchestrator.php`
