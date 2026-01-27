---
name: workflows:analyze
description: "Exhaustive analysis of project architecture to create improvement plans"
argument_hint: [--layer=all|domain|application|orchestrator|infrastructure] [--output=spec|report] [--depth=quick|standard|deep]
---

# Multi-Agent Workflow: Architecture Analyzer

## Philosophy

> **"You cannot improve what you cannot see. Deep analysis precedes meaningful refactoring."**

Before any refactoring or scaling effort, you need a complete understanding of the current state. This command performs an exhaustive analysis of all project layers, identifying:

- **Violations**: Code that breaks architectural rules
- **Coupling**: Tight dependencies that prevent scalability
- **Complexity**: Classes/methods exceeding complexity thresholds
- **Debt**: Technical debt patterns and anti-patterns
- **Opportunities**: Areas ripe for improvement

## Usage

```bash
# Full analysis (all layers, standard depth)
/workflows:analyze

# Analyze specific layer
/workflows:analyze --layer=orchestrator

# Quick scan for high-level issues
/workflows:analyze --depth=quick

# Deep analysis with full metrics
/workflows:analyze --depth=deep

# Generate spec document for planning
/workflows:analyze --output=spec
```

## Arguments

- `--layer`: Target layer for analysis (default: `all`)
  - `all` - Analyze entire codebase
  - `domain` - Domain entities, value objects, events
  - `application` - Use cases, DTOs, transformers
  - `orchestrator` - Chain handlers, fetchers
  - `infrastructure` - Clients, services, traits

- `--output`: Output format (default: `report`)
  - `report` - Markdown report to console
  - `spec` - Generate spec document in `.claude/specs/`

- `--depth`: Analysis thoroughness (default: `standard`)
  - `quick` - High-level issues only (~2 min)
  - `standard` - Balanced analysis (~5 min)
  - `deep` - Full metrics and recommendations (~10 min)

## What This Command Does

### 1. Layer Purity Analysis
Validates that each layer respects its boundaries:
- **Application Layer**: No HTTP clients, no infrastructure dependencies
- **Orchestrator Layer**: HTTP calls only in `Service/` subdirectory
- **Infrastructure Layer**: No business logic
- **Controller Layer**: Thin, only orchestrator injection

### 2. Class Complexity Analysis
For each class, evaluates:
- Lines of code (threshold: 300)
- Methods count (threshold: 15)
- Cyclomatic complexity per method (threshold: 10)
- Dependencies count (threshold: 7)
- Public methods ratio

### 3. SOLID Violations Detection
Identifies violations of:
- **S**ingle Responsibility: Classes doing too much
- **O**pen/Closed: Modification instead of extension
- **L**iskov Substitution: Interface contract violations
- **I**nterface Segregation: Fat interfaces
- **D**ependency Inversion: Concrete dependencies

### 4. DDD Compliance Check
Validates against `rules/ddd_rules.md`:
- Entities have proper identity
- Value Objects are immutable
- Domain events follow naming conventions
- Anti-corruption layer properly isolates external models

### 5. Pattern Compliance
Checks implementation of required patterns:
- Chain of Responsibility (Orchestrators)
- Strategy Pattern (DataTransformers)
- Template Method (Base classes)
- Compiler Passes (Auto-registration)

### 6. Coupling Analysis
Identifies:
- Circular dependencies
- Hidden coupling through shared state
- Feature envy (class using another class's data too much)
- God classes (too many responsibilities)

### 7. Scalability Assessment
Evaluates:
- Extensibility points
- Plugin architecture readiness
- Configuration flexibility
- Testing isolation

## Execution Steps

### Step 1: Gather Inventory
```bash
# Collect all PHP classes
find src/ -name "*.php" -type f
```

Build inventory of:
- All classes with their namespace
- Layer assignment based on path
- Interface implementations
- Service tags and compiler pass registration

### Step 2: Run Layer Purity Checks

For each layer, verify allowed dependencies:

```
┌─────────────────────────────────────────────────────────────┐
│                    LAYER PURITY MATRIX                      │
├───────────────────┬─────────────────────────────────────────┤
│ Layer             │ Allowed Dependencies                    │
├───────────────────┼─────────────────────────────────────────┤
│ Exception         │ None (no service dependencies)          │
│ Application/DTO   │ None (pure data structures)             │
│ Application/Trans │ DTOs, domain models (NO HTTP clients)   │
│ Application/Svc   │ Transformers, DTOs (NO HTTP clients)    │
│ Orchestrator/Chain│ Fetchers, Aggregators, Transformers     │
│ Orchestrator/Svc  │ HTTP Clients ✓, Domain models          │
│ Infrastructure    │ External libraries, HTTP clients        │
│ Controller        │ OrchestratorChain ONLY                  │
└───────────────────┴─────────────────────────────────────────┘
```

### Step 3: Analyze Class Metrics

For each class, calculate:

```php
ClassMetrics {
    string $fqcn;           // Fully qualified class name
    string $layer;          // Assigned layer
    int $lines;             // Total lines
    int $methods;           // Method count
    int $dependencies;      // Constructor dependencies
    int $maxComplexity;     // Highest method complexity
    float $publicRatio;     // Public methods / total methods
    array $violations;      // List of rule violations
}
```

### Step 4: Detect Patterns

Scan for:

| Pattern | Detection Method |
|---------|------------------|
| Chain of Responsibility | Classes implementing `*OrchestratorInterface` |
| Strategy | Classes with `canTransform()` + service tags |
| Template Method | Abstract classes with concrete + abstract methods |
| Compiler Pass | Classes in `DependencyInjection/Compiler/` |

### Step 5: Generate Findings

Categorize findings by severity:

```
🔴 CRITICAL - Blocks scaling, must fix immediately
🟠 HIGH     - Significant debt, fix before new features
🟡 MEDIUM   - Should fix, but not blocking
🟢 LOW      - Nice to have improvements
```

### Step 6: Create Improvement Plan

For each finding, generate:
- **What**: Description of the issue
- **Why**: Business/technical impact
- **How**: Recommended fix approach
- **Effort**: Estimated complexity (S/M/L/XL)
- **Priority**: Based on impact vs effort

## Analysis Checklist

### Pre-Analysis
- [ ] Read `rules/project_specific.md` for context
- [ ] Read `rules/ddd_rules.md` for layer rules
- [ ] Verify test suite passes (`make test_unit`)
- [ ] Check existing architecture tests (`./bin/phpunit --group architecture`)

### During Analysis
- [ ] Document all violations found
- [ ] Note patterns that work well (preserve these)
- [ ] Identify quick wins (low effort, high impact)
- [ ] Flag breaking changes that need migration

### Post-Analysis
- [ ] Prioritize findings by impact
- [ ] Group related issues
- [ ] Create actionable recommendations
- [ ] Generate spec document (if `--output=spec`)

## Output Format

### Console Report

```markdown
# Architecture Analysis Report

**Generated**: 2026-01-27T14:30:00Z
**Scope**: All layers
**Depth**: Standard

## Summary

| Metric | Value | Threshold | Status |
|--------|-------|-----------|--------|
| Classes Analyzed | 127 | - | - |
| Layer Violations | 3 | 0 | 🔴 |
| Complexity Issues | 7 | 5 | 🟠 |
| SOLID Violations | 12 | 10 | 🟡 |
| DDD Compliance | 89% | 95% | 🟡 |

## Critical Findings (🔴)

### 1. HTTP Client in Application Layer
**Location**: `src/Application/Service/ResponseAggregator.php:45`
**Violation**: Injects `QueryLegacyClient` (HTTP dependency)
**Impact**: Breaks layer purity, makes testing difficult
**Fix**: Move HTTP call to Orchestrator, pass data as DTO
**Effort**: M

### 2. ...

## High Priority Findings (🟠)

### 1. God Class: EditorialOrchestrator
**Location**: `src/Orchestrator/Chain/EditorialOrchestrator.php`
**Metrics**: 537 lines, 23 methods, 12 dependencies
**Impact**: Difficult to maintain, hard to extend
**Fix**: Extract EmbeddedContentFetcher, SignatureFetcher
**Effort**: L

## Recommendations

1. **Immediate**: Fix 3 layer violations
2. **Short-term**: Split EditorialOrchestrator
3. **Medium-term**: Replace array returns with DTOs
4. **Long-term**: Implement event sourcing for changes
```

### Spec Document (--output=spec)

Creates `.claude/specs/architecture-analysis-[timestamp].md`:

```markdown
# Architecture Analysis Spec

## Context
[Auto-generated analysis context]

## Findings Summary
[Categorized findings]

## Improvement Plan

### Phase 1: Critical Fixes (Week 1)
- [ ] Task 1
- [ ] Task 2

### Phase 2: High Priority (Week 2-3)
- [ ] Task 3
- [ ] Task 4

### Phase 3: Medium Priority (Week 4+)
- [ ] Task 5
- [ ] Task 6

## Success Criteria
- [ ] All architecture tests pass
- [ ] No layer violations
- [ ] Complexity under threshold
- [ ] 95% DDD compliance
```

## Integration with Workflow

This command is typically used:

1. **Before `/workflows:plan`**: Understand what needs fixing
2. **During refactoring**: Track progress on improvements
3. **After major changes**: Verify no new violations introduced

## Skill Dependencies

This command uses:
- `layer-validator` - For layer purity checks
- `code-simplifier` - For complexity analysis (optional)

## Next Steps

After running analysis:

1. **Review findings** with stakeholders
2. **Create feature** via `/workflows:plan architecture-improvements`
3. **Execute fixes** via `/workflows:work --mode=layers`
4. **Validate** via `/workflows:review`

## Tips

- Start with `--depth=quick` to get a high-level view
- Use `--layer=X` to focus on specific problem areas
- Run `--output=spec` to create actionable planning documents
- Re-run after fixes to measure improvement
