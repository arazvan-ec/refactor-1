# Architecture Analyzer - Analysis Queries

Specific queries and patterns for analyzing SNAAPI (Symfony 6.4 / PHP 8.2+).

## Inventory Queries

### Count Classes by Layer

```bash
# Total PHP files
find src/ -name "*.php" -type f | wc -l

# By top-level directory
for dir in Application Controller DependencyInjection EventSubscriber Exception Infrastructure Orchestrator; do
  echo "$dir: $(find src/$dir -name "*.php" -type f 2>/dev/null | wc -l)"
done
```

### List All Classes with Full Paths

```bash
# All classes in src/
find src/ -name "*.php" -type f | sort
```

## Layer Purity Queries

### Find HTTP Client Injections

```bash
# Find classes injecting *Client (HTTP clients)
grep -r "private.*Client \$" src/ --include="*.php"

# Specifically in Application layer (VIOLATION)
grep -r "private.*Client \$" src/Application/ --include="*.php"

# In Infrastructure/Service (VIOLATION - should be in Orchestrator/Service)
grep -r "private.*Client \$" src/Infrastructure/Service/ --include="*.php"

# In EventSubscriber (VIOLATION)
grep -r "private.*Client \$" src/EventSubscriber/ --include="*.php"
```

### Validate Controller Dependencies

```bash
# Controllers should only inject OrchestratorChain*
grep -A 20 "__construct" src/Controller/**/*.php | grep "private"

# Should NOT see direct service injections
```

### Check Exception Layer Purity

```bash
# Exceptions should have NO constructor dependencies
grep -A 10 "__construct" src/Exception/*.php

# Should only see parent::__construct() calls
```

## Complexity Queries

### Lines of Code per File

```bash
# Top 20 largest files
find src/ -name "*.php" -exec wc -l {} \; | sort -rn | head -20

# Files over 300 lines (threshold)
find src/ -name "*.php" -exec sh -c 'lines=$(wc -l < "$1"); if [ $lines -gt 300 ]; then echo "$lines $1"; fi' _ {} \;
```

### Method Count per Class

```bash
# Count "function " occurrences (approximate method count)
for f in $(find src/ -name "*.php"); do
  count=$(grep -c "function " "$f" 2>/dev/null)
  if [ "$count" -gt 15 ]; then
    echo "$count methods: $f"
  fi
done | sort -rn
```

### Constructor Dependency Count

```bash
# Show constructor params for Orchestrator classes
grep -A 20 "__construct" src/Orchestrator/**/*.php

# Count dependencies (lines with "private" in constructor)
for f in $(find src/Orchestrator -name "*.php"); do
  count=$(grep -A 20 "__construct" "$f" | grep -c "private.*\$")
  if [ "$count" -gt 5 ]; then
    echo "$count deps: $f"
  fi
done
```

## SOLID Violation Queries

### Single Responsibility - God Classes

```bash
# Classes with both "fetch" and "transform" methods
grep -l "function.*fetch" src/**/*.php | xargs grep -l "function.*transform"

# Classes with multiple unrelated method prefixes
grep -h "public function " src/Orchestrator/**/*.php | sort | uniq
```

### Dependency Inversion - Concrete Dependencies

```bash
# Find concrete class dependencies (not interfaces)
# Pattern: private SomeClass $var (not SomeInterface $var)
grep -r "private [A-Z][a-zA-Z]*[^Interface] \$" src/ --include="*.php" | grep -v "Interface \$"
```

### Open/Closed - Switch on Types

```bash
# Find switch statements (potential O/C violations)
grep -rn "switch.*\$type" src/ --include="*.php"
grep -rn "switch.*getType" src/ --include="*.php"

# Find if-else chains on type
grep -rn "if.*instanceof" src/ --include="*.php"
```

## DDD Compliance Queries

### DataTransformer Pattern

```bash
# All DataTransformers
find src/Application/DataTransformer -name "*DataTransformer.php"

# Check they implement interface
grep -l "implements.*DataTransformerInterface" src/Application/DataTransformer/**/*.php

# Check they have canTransform method
grep -l "function canTransform" src/Application/DataTransformer/**/*.php
```

### Service Tags

```bash
# Find service definitions with tags
grep -r "app.data_transformer" config/
grep -r "app.media_data_transformer" config/
grep -r "app.editorial_orchestrator" config/
```

### Compiler Passes

```bash
# List all compiler passes
find src/DependencyInjection/Compiler -name "*.php" -type f
```

## Pattern Compliance Queries

### Chain of Responsibility

```bash
# Find all orchestrator interfaces
grep -r "interface.*OrchestratorInterface" src/ --include="*.php"

# Find implementations
grep -r "implements.*OrchestratorInterface" src/ --include="*.php"

# Check handler/router
grep -r "class.*ChainHandler" src/ --include="*.php"
```

### Strategy Pattern

```bash
# Find strategy dispatch (handler calling strategies)
grep -rn "canTransform\|supports" src/Application/DataTransformer/ --include="*.php"
```

### Template Method

```bash
# Find abstract classes with mix of abstract/concrete methods
grep -l "abstract class" src/ -r --include="*.php" | xargs grep -l "abstract.*function"
```

## Coupling Queries

### Find Circular Dependencies

```bash
# Classes in A that depend on B, and B depends on A
# Manual check: list deps for suspicious pairs

# High coupling indicator: mutual use statements
grep -h "^use " src/Orchestrator/Chain/EditorialOrchestrator.php
# Then check if those classes also use EditorialOrchestrator
```

### Feature Envy Indicators

```bash
# Methods that heavily access another class
# Look for patterns like $this->other->getX()->getY()->getZ()
grep -rn "\->get.*\->get.*\->get" src/ --include="*.php"
```

## Architecture Test Queries

### Run Existing Architecture Tests

```bash
# All architecture tests
./bin/phpunit --group architecture

# Specific test
./bin/phpunit tests/Architecture/TransformationLayerArchitectureTest.php
```

### List Architecture Test Classes

```bash
find tests/Architecture -name "*.php" -type f
```

## Quick Health Check

```bash
# One-liner health summary
echo "=== SNAAPI Architecture Health ===" && \
echo "Total classes: $(find src/ -name '*.php' | wc -l)" && \
echo "HTTP in App layer: $(grep -r 'Client \$' src/Application/ --include='*.php' | wc -l) violations" && \
echo "Files >300 LOC: $(find src/ -name '*.php' -exec sh -c 'lines=$(wc -l < "$1"); [ $lines -gt 300 ] && echo 1' _ {} \; | wc -l)" && \
echo "Architecture tests: $(./bin/phpunit --group architecture 2>&1 | grep -E 'OK|FAILURES')"
```

## Output Formatting

### Generate Markdown Table

```bash
# Classes with metrics (example)
echo "| Class | LOC | Methods |"
echo "|-------|-----|---------|"
for f in $(find src/Orchestrator -name "*.php"); do
  loc=$(wc -l < "$f")
  methods=$(grep -c "function " "$f")
  name=$(basename "$f" .php)
  echo "| $name | $loc | $methods |"
done
```

## Notes

- All queries assume you're in the project root (`/home/user/refactor-1`)
- Adjust paths if directory structure changes
- Some counts are approximate (grep-based)
- For exact metrics, use PHPStan or dedicated tools
