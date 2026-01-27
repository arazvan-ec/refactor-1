# Architecture Analyzer Skill

Performs exhaustive analysis of project architecture to identify violations, complexity issues, and improvement opportunities across all DDD layers.

## What This Skill Does

- **Layer Purity Analysis**: Validates dependencies respect layer boundaries
- **Complexity Metrics**: Calculates LOC, methods, cyclomatic complexity per class
- **SOLID Detection**: Identifies Single Responsibility, Dependency Inversion violations
- **DDD Compliance**: Validates entities, value objects, anti-corruption layer
- **Pattern Verification**: Checks Chain of Responsibility, Strategy, Template Method
- **Coupling Analysis**: Detects circular dependencies, god classes, feature envy
- **Scalability Assessment**: Evaluates extensibility and plugin readiness

## When to Use

- Before planning a major refactoring effort
- When onboarding to understand codebase health
- After significant changes to verify no regressions
- During architecture reviews
- To generate improvement specs for `/workflows:plan`

## How to Use

### Via Slash Command
```
/workflows:analyze [--layer=all] [--depth=standard] [--output=report]
```

### Manual Invocation
Follow the step-by-step analysis protocol below.

---

## Analysis Protocol

### Phase 1: Inventory Collection

#### 1.1 Collect All Classes

```bash
# Find all PHP classes
find src/ -name "*.php" -type f | head -200

# Count by directory
find src/ -name "*.php" -type f | cut -d'/' -f2 | sort | uniq -c
```

#### 1.2 Build Layer Map

Assign each class to its architectural layer:

| Path Pattern | Layer |
|--------------|-------|
| `src/Exception/` | Exception (no deps) |
| `src/Application/DTO/` | Application/DTO |
| `src/Application/DataTransformer/` | Application/Transformer |
| `src/Application/Service/` | Application/Service |
| `src/Orchestrator/Chain/` | Orchestrator/Chain |
| `src/Orchestrator/Service/` | Orchestrator/Service |
| `src/Infrastructure/Client/` | Infrastructure/Client |
| `src/Infrastructure/Service/` | Infrastructure/Service |
| `src/Infrastructure/Trait/` | Infrastructure/Trait |
| `src/Infrastructure/Enum/` | Infrastructure/Enum |
| `src/Infrastructure/Config/` | Infrastructure/Config |
| `src/Controller/` | Controller |
| `src/EventSubscriber/` | EventSubscriber |
| `src/DependencyInjection/` | DependencyInjection |

#### 1.3 Extract Dependencies

For each class, extract constructor dependencies:

```php
// Pattern to find
public function __construct(
    private SomeService $service,
    private AnotherService $another,
)
```

---

### Phase 2: Layer Purity Validation

#### 2.1 Dependency Rules Matrix

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         ALLOWED DEPENDENCIES                                 │
├─────────────────────────┬───────────────────────────────────────────────────┤
│ Layer                   │ Can Depend On                                     │
├─────────────────────────┼───────────────────────────────────────────────────┤
│ Exception               │ NOTHING (pure exceptions, no service deps)        │
│ Application/DTO         │ NOTHING (pure data structures)                    │
│ Application/Transformer │ DTOs, Interfaces, Enums (NO *Client)              │
│ Application/Service     │ Transformers, DTOs, Interfaces (NO *Client)       │
│ Orchestrator/Chain      │ Orchestrator/Service, Application/*, Interfaces   │
│ Orchestrator/Service    │ Infrastructure/Client ✓, DTOs, Interfaces         │
│ Infrastructure/Client   │ External libraries, HTTP clients                  │
│ Infrastructure/Service  │ Enums, Config, Traits (NO *Client)                │
│ Controller              │ OrchestratorChain ONLY (thin controllers)         │
│ EventSubscriber         │ Services (NO *Client)                             │
└─────────────────────────┴───────────────────────────────────────────────────┘
```

#### 2.2 Violation Detection

For each class, check if any dependency violates the rules:

```
VIOLATION if:
  - Application/* depends on *Client
  - Infrastructure/Service depends on *Client
  - EventSubscriber depends on *Client
  - Exception has ANY constructor dependency
  - Controller depends on anything other than OrchestratorChain*
```

#### 2.3 Report Format

```markdown
## Layer Purity Violations

| Class | Layer | Invalid Dependency | Rule Violated |
|-------|-------|-------------------|---------------|
| ResponseAggregator | Application/Service | QueryLegacyClient | No HTTP in App layer |
```

---

### Phase 3: Complexity Analysis

#### 3.1 Thresholds

| Metric | Green | Yellow | Red |
|--------|-------|--------|-----|
| Lines of Code (LOC) | ≤150 | 151-300 | >300 |
| Methods per Class | ≤10 | 11-15 | >15 |
| Dependencies | ≤5 | 6-7 | >7 |
| Cyclomatic Complexity | ≤5 | 6-10 | >10 |
| Public Methods Ratio | ≤0.5 | 0.5-0.7 | >0.7 |

#### 3.2 Analysis Commands

```bash
# Count lines per file
wc -l src/**/*.php | sort -n | tail -20

# Find files with most methods (approximate)
grep -c "function " src/**/*.php | sort -t: -k2 -n | tail -20

# Find constructor dependencies
grep -A 20 "__construct" src/Orchestrator/Chain/*.php
```

#### 3.3 Complexity Report Format

```markdown
## High Complexity Classes

| Class | LOC | Methods | Deps | Max CC | Status |
|-------|-----|---------|------|--------|--------|
| EditorialOrchestrator | 537 | 23 | 12 | 15 | 🔴 |
| ResponseAggregator | 289 | 14 | 8 | 8 | 🟠 |
```

---

### Phase 4: SOLID Violations

#### 4.1 Single Responsibility (S)

**Detection signals**:
- Class name contains "And" or "Or"
- Class has methods from multiple domains
- Class has >5 dependencies
- Class has >15 methods

**Common violations in this codebase**:
- Orchestrators doing both fetching AND transformation
- Services mixing HTTP calls with data processing

#### 4.2 Open/Closed Principle (O)

**Detection signals**:
- Switch statements on type strings
- Direct modification instead of extension
- Missing strategy pattern where types vary

**Look for**:
```php
// Bad: modification needed for new types
switch ($type) {
    case 'photo': ...
    case 'video': ...
    // Need to add case for new type!
}

// Good: extension via new classes
$handler = $this->handlerChain->getHandler($type);
$handler->handle($data);
```

#### 4.3 Dependency Inversion (D)

**Detection signals**:
- Concrete class in constructor (not interface)
- `new` keyword inside service methods
- Hard-coded class references

**Check**:
```bash
# Find concrete dependencies (should be interfaces)
grep -r "private [A-Z][a-zA-Z]*Client \$" src/Application/
```

---

### Phase 5: DDD Compliance

#### 5.1 Entity Validation

Entities (`src/**/Entity/`) should have:
- [ ] Private properties
- [ ] Factory methods (`create()`, `reconstitute()`)
- [ ] No setters (use behavior methods)
- [ ] Identity via ID value object

#### 5.2 Value Object Validation

Value Objects (`src/**/ValueObject/`) should have:
- [ ] Immutable (readonly or no setters)
- [ ] Validation in constructor
- [ ] `equals()` method
- [ ] `__toString()` method

#### 5.3 Anti-Corruption Layer

DataTransformers should:
- [ ] NOT inject HTTP clients
- [ ] Receive pre-fetched data
- [ ] Return clean DTOs or arrays
- [ ] Isolate external model structure

---

### Phase 6: Pattern Verification

#### 6.1 Chain of Responsibility

**Expected structure**:
```
src/Orchestrator/
├── OrchestratorChainHandler.php  # Router
└── Chain/
    ├── EditorialOrchestrator.php # Handler 1
    ├── MultimediaOrchestrator.php # Handler 2
    └── ...
```

**Verification**:
- Each handler implements `*OrchestratorInterface`
- Registered via Compiler Pass
- Has `supports()` or similar routing method

#### 6.2 Strategy Pattern (DataTransformers)

**Expected structure**:
```
src/Application/DataTransformer/
├── BodyElementDataTransformerHandler.php  # Dispatcher
└── Apps/Body/
    ├── ParagraphDataTransformer.php       # Strategy 1
    ├── SubHeadDataTransformer.php         # Strategy 2
    └── ...
```

**Verification**:
- Each transformer implements common interface
- Has `canTransform($type)` method
- Tagged with `app.data_transformer`

#### 6.3 Template Method

**Look for**:
- Abstract base classes with concrete + abstract methods
- Example: `ElementTypeDataTransformer`

---

### Phase 7: Coupling Analysis

#### 7.1 Afferent/Efferent Coupling

```
Afferent (Ca) = How many classes depend on this class
Efferent (Ce) = How many classes this class depends on

Instability = Ce / (Ca + Ce)
  - 0.0 = Highly stable (many depend on it)
  - 1.0 = Highly unstable (depends on many)
```

#### 7.2 God Class Detection

**Signals**:
- >500 LOC
- >15 methods
- >7 dependencies
- Methods touch multiple domains

#### 7.3 Feature Envy

**Signals**:
- Method uses more data from another class than its own
- Extensive `$other->getX()` chains

---

### Phase 8: Generate Recommendations

#### 8.1 Prioritization Matrix

```
             │ Low Effort │ High Effort │
─────────────┼────────────┼─────────────┤
High Impact  │ DO FIRST   │ PLAN        │
─────────────┼────────────┼─────────────┤
Low Impact   │ DO LATER   │ DON'T DO    │
```

#### 8.2 Recommendation Format

```markdown
### [Finding Title]

**Location**: `src/Path/To/Class.php:line`
**Severity**: 🔴 CRITICAL | 🟠 HIGH | 🟡 MEDIUM | 🟢 LOW
**Category**: Layer Violation | Complexity | SOLID | DDD | Coupling

**Issue**:
[What is wrong]

**Impact**:
[Why it matters - testability, maintainability, scalability]

**Recommendation**:
[How to fix it]

**Effort**: S | M | L | XL
**Priority**: 1-10 (10 = do immediately)
```

---

## Output Templates

### Quick Scan Output

```markdown
# Quick Architecture Scan

**Date**: [timestamp]
**Classes**: [count]
**Health Score**: [X/100]

## Critical Issues (Fix Immediately)
- [Issue 1]
- [Issue 2]

## Warnings (Plan to Fix)
- [Warning 1]
- [Warning 2]

## Recommendations
1. [Top priority action]
2. [Second priority action]
```

### Standard Analysis Output

```markdown
# Architecture Analysis Report

## Executive Summary
[2-3 sentences on overall health]

## Metrics Dashboard

| Category | Score | Target | Status |
|----------|-------|--------|--------|
| Layer Purity | X% | 100% | [status] |
| Complexity | X | <10 avg | [status] |
| SOLID Compliance | X% | >90% | [status] |
| DDD Compliance | X% | >95% | [status] |

## Findings by Severity

### 🔴 Critical (X issues)
[List with details]

### 🟠 High (X issues)
[List with details]

### 🟡 Medium (X issues)
[List with details]

### 🟢 Low (X issues)
[List with details]

## Improvement Roadmap

### Phase 1: Immediate (This Sprint)
- [ ] [Task]

### Phase 2: Short-term (2-4 weeks)
- [ ] [Task]

### Phase 3: Medium-term (1-3 months)
- [ ] [Task]
```

### Deep Analysis Output

Includes all of the above plus:
- Full class-by-class metrics table
- Dependency graph (text representation)
- Historical comparison (if previous analysis exists)
- Risk assessment for each finding
- Migration guides for breaking changes

---

## Integration with Other Skills

| Skill | Integration Point |
|-------|-------------------|
| `layer-validator` | Called for layer purity checks |
| `test-runner` | Run architecture tests first |
| `code-simplifier` | Apply to identified complex classes |
| `checkpoint` | Save analysis state for long sessions |

## Failure Handling

```
If analysis incomplete due to context limits:
1. Save partial results to `.claude/specs/partial-analysis.md`
2. Document which layers were analyzed
3. Resume with `/workflows:analyze --layer=[remaining]`
```

## Examples

### Example 1: Quick Health Check

```bash
/workflows:analyze --depth=quick
```

Output:
```
# Quick Architecture Scan

Health Score: 72/100

Critical Issues:
1. ResponseAggregator injects HTTP client (layer violation)
2. EditorialOrchestrator exceeds complexity threshold

Recommendations:
1. Extract HTTP calls from Application layer
2. Split EditorialOrchestrator into focused services
```

### Example 2: Generate Spec for Planning

```bash
/workflows:analyze --output=spec --depth=deep
```

Creates: `.claude/specs/architecture-analysis-2026-01-27.md`

Then use with:
```bash
/workflows:plan architecture-improvements --spec=architecture-analysis-2026-01-27
```
