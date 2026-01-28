# Multi-Agent Workflow Plugin

A **compound engineering** framework for Claude Code that coordinates multiple AI agents working in parallel on software development.

> **"Each unit of engineering work should make subsequent units easier—not harder"**

## Features

- **16 Specialized Agents** in 5 categories: roles, review, research, workflow, design
- **15 Workflow Commands**: Core (4), Collaboration (4), Parallel Agents (3), Quality (4)
- **10 Skills**: Core, Quality, Workflow, and Compound automation
- **3 Parallelization Modes**: By roles, by layers (DDD), or by stacks
- **Quality Gates**: TDD enforcement, trust model, spec validation
- **Compound Learning**: Capture insights from each feature

## Installation

```bash
/plugin marketplace add https://github.com/arazvan-ec/workflow
/plugin install multi-agent-workflow
```

## Quick Start

```bash
# Plan a feature (80% of compound engineering)
/workflows:plan user-authentication

# Execute with specific mode
/workflows:work --mode=roles --role=backend user-authentication
/workflows:work --mode=layers --layer=domain user-authentication

# Review before merge
/workflows:review user-authentication

# Capture learnings (compound effect)
/workflows:compound user-authentication
```

## Core Workflows (4 Phases)

| Phase | Command | Purpose | Time |
|-------|---------|---------|------|
| **Plan** | `/workflows:plan` | Convert ideas into implementable strategies | 40% |
| **Work** | `/workflows:work` | Execute with parallelization modes | 40% |
| **Review** | `/workflows:review` | Multi-agent review before merge | 15% |
| **Compound** | `/workflows:compound` | Capture insights for future work | 5% |

## Parallelization Modes

### By Role (Standard)
```
Planner → Backend + Frontend (parallel) → QA
```

### By Layer (DDD)
```
Domain + Application + Infrastructure (parallel)
```

### By Stack
```
Backend complete + Frontend complete (parallel)
```

## Agent Categories

### Roles (4 agents)
| Agent | Purpose |
|-------|---------|
| `planner` | Define features, create contracts, coordinate |
| `backend` | Implement API with DDD, write tests |
| `frontend` | Implement UI, responsive design |
| `qa` | Review, test, approve/reject |

### Review (4 agents)
| Agent | Purpose |
|-------|---------|
| `security-review` | OWASP, vulnerabilities |
| `performance-review` | Speed, optimization |
| `ddd-compliance` | Layer separation, DDD rules |
| `code-review-ts` | TypeScript/React patterns |

### Research (3 agents)
| Agent | Purpose |
|-------|---------|
| `codebase-analyzer` | Understand structure, patterns |
| `git-historian` | Extract learnings from history |
| `dependency-auditor` | Security, updates |

### Workflow (3 agents)
| Agent | Purpose |
|-------|---------|
| `bug-reproducer` | Systematic bug reproduction |
| `spec-analyzer` | Validate vs specifications |
| `style-enforcer` | Code style automation |

### Design (2 agents)
| Agent | Purpose |
|-------|---------|
| `api-designer` | API contracts |
| `ui-verifier` | UI vs specs |

## Multi-Agent Commands

### Core Workflows (4)
| Command | Description |
|---------|-------------|
| `/workflows:plan <feature>` | Convert ideas into implementable strategies |
| `/workflows:work <feature>` | Execute with parallelization modes |
| `/workflows:review <feature>` | Multi-agent review before merge |
| `/workflows:compound <feature>` | Capture insights for future work |

### Collaboration (4)
| Command | Description |
|---------|-------------|
| `/workflows:role <role> <feature>` | Work as a specific role |
| `/workflows:sync <feature>` | Synchronize state |
| `/workflows:status <feature>` | View all roles' status |
| `/workflows:checkpoint <feature>` | Save progress checkpoint |

### Parallel Agents (3) - NEW
| Command | Description |
|---------|-------------|
| `/workflows:parallel <feature>` | Launch multiple agents in parallel with git worktrees |
| `/workflows:monitor` | Monitor status of parallel agents in real-time |
| `/workflows:progress` | Track session progress for long-running agents |

### Quality & Enforcement (4) - NEW
| Command | Description |
|---------|-------------|
| `/workflows:tdd <check\|display\|generate>` | TDD compliance and test templates |
| `/workflows:trust <file\|--task>` | Check trust level and supervision requirements |
| `/workflows:validate <spec>` | Validate YAML specs against JSON schemas |
| `/workflows:interview <feature\|api>` | Create specs through guided interview |

## Skills

### Core
- **consultant**: AI-powered project analysis
- **checkpoint**: Quality-gated progress saving
- **git-sync**: Repository synchronization

### Quality
- **test-runner**: Execute test suites
- **coverage-checker**: Validate coverage thresholds
- **lint-fixer**: Auto-fix code style

### Workflow
- **worktree-manager**: Parallel development with worktrees
- **commit-formatter**: Conventional commits

### Compound
- **changelog-generator**: Generate changelogs
- **layer-validator**: DDD layer validation

## Key Patterns

### Ralph Wiggum Pattern (Auto-Correction Loop)
```python
while tests_failing and iterations < 10:
    fix_code()
    run_tests()
    if passing: checkpoint_complete()

if iterations >= 10:
    mark_blocked()
    document_for_help()
```

### Compound Capture Pattern
After each feature:
1. Review commits and PRs
2. Identify patterns/anti-patterns
3. Update project rules
4. Document in compound_log.md

## Project Structure

### Plugin Files (Generic - updatable from workflow repo)
```
.claude/
├── .claude-plugin/plugin.json
├── CLAUDE.md
├── README.md
├── agents/
│   ├── roles/           # backend, frontend, planner, qa
│   ├── review/          # code, security, ddd, performance
│   ├── research/        # analyzer, git, dependencies
│   ├── workflow/        # bug, spec, style
│   └── design/          # api, ui
├── commands/workflows/  # 15 commands
│   ├── plan.md          # Core
│   ├── work.md          # Core
│   ├── review.md        # Core
│   ├── compound.md      # Core
│   ├── checkpoint.md    # Collaboration
│   ├── role.md          # Collaboration
│   ├── status.md        # Collaboration
│   ├── sync.md          # Collaboration
│   ├── parallel.md      # Parallel (NEW)
│   ├── monitor.md       # Parallel (NEW)
│   ├── progress.md      # Parallel (NEW)
│   ├── tdd.md           # Quality (NEW)
│   ├── trust.md         # Quality (NEW)
│   ├── validate.md      # Quality (NEW)
│   └── interview.md     # Quality (NEW)
└── skills/              # 10 skills
```

### Project-Specific Files (DO NOT UPDATE from repo)
```
.claude/
├── rules/               # Project-specific rules
│   ├── ddd_rules.md
│   ├── global_rules.md
│   └── project_specific.md
├── features/            # Feature work in progress
├── specs/               # Architecture analysis docs
└── skills/
    └── code-simplifier.md  # Project-specific skill
```

## State Management

All roles communicate via `50_state.md`:

```markdown
## Backend Engineer
**Status**: IN_PROGRESS
**Checkpoint**: Domain layer complete
**Tests**: 15/15 passing, 92% coverage
```

Status values: `PENDING`, `IN_PROGRESS`, `BLOCKED`, `WAITING_API`, `COMPLETED`, `APPROVED`, `REJECTED`

## Best Practices

1. **80% Planning, 20% Execution**: Invest in `/workflows:plan`
2. **One role per session**: Don't switch roles mid-conversation
3. **Sync before work**: Always pull latest changes first
4. **TDD always**: Write tests before implementation
5. **Compound always**: Run `/workflows:compound` after each feature

## The Compound Effect

```
Feature 1: 5 hours + 3 patterns captured
Feature 2: 3 hours (reused 2 patterns)
Feature 3: 2.5 hours (reused 4 patterns)
Feature 4: 2 hours (reused 5 patterns)

Time saved: 37.5%
```

## Integration

Works best with:
- **Git**: For synchronization between agents
- **Tilix/tmux**: For running multiple roles in parallel
- **Symfony/React**: Optimized for this stack (but adaptable)
- **DDD Architecture**: Domain-Driven Design patterns

## License

MIT

## Author

arazvan-ec

---

**Version**: 2.1.0
**Aligned with**: Compound Engineering principles
**Source**: https://github.com/arazvan-ec/workflow
**Last updated**: 2026-01-28
