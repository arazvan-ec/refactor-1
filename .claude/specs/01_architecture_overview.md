# SNAAPI - Technical Specifications

**Project**: SNAAPI - API Gateway for Mobile Apps
**Version**: 1.0
**Last Updated**: 2026-01-26

---

## 1. Architecture Overview

SNAAPI is an **API Gateway** with a clear two-layer architecture:

```
┌─────────────────────────────────────────────────────────────────┐
│                         Mobile Apps                             │
└─────────────────────────┬───────────────────────────────────────┘
                          │ HTTP Request
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                    SNAAPI API Gateway                           │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              LAYER 1: HTTP Reading Layer                  │  │
│  │                                                           │  │
│  │  Controller → Orchestrator → External Clients → Promises  │  │
│  │                                                           │  │
│  │  - HTTPlug + Guzzle7 Adapter                             │  │
│  │  - Async Promises for parallel requests                   │  │
│  │  - 8 External Service Clients                            │  │
│  └───────────────────────────────────────────────────────────┘  │
│                          │                                      │
│                          ▼                                      │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              LAYER 2: JSON Response Layer                 │  │
│  │                                                           │  │
│  │  DataTransformers → DTOs → JSON Response                  │  │
│  │                                                           │  │
│  │  - Anti-Corruption Layer (isolates external models)       │  │
│  │  - 31 DataTransformers (Strategy Pattern)                │  │
│  │  - Chain of Responsibility for type routing              │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                          │ JSON Response
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│    External Microservices (Editorial, Multimedia, Tags, etc.)   │
└─────────────────────────────────────────────────────────────────┘
```

### Key Characteristics

| Aspect | Description |
|--------|-------------|
| **Type** | API Gateway (aggregation, no local persistence) |
| **Data Storage** | None - all data fetched from external services |
| **Primary Function** | Aggregate + Transform + Cache |
| **Architecture Style** | DDD-inspired with Anti-Corruption Layer |

---

## 2. Layer Responsibilities

### Layer 1: HTTP Reading Layer

**Purpose**: Fetch data from external microservices efficiently.

**Components**:
- `Controller` - HTTP entry point (thin, delegates immediately)
- `Orchestrator` - Coordinates multiple service calls
- `External Clients` - ec/* libraries (editorial, multimedia, etc.)
- `PromiseResolver` - Resolves async promises in parallel

**Responsibilities**:
- Make HTTP requests to external services
- Handle async/parallel execution via Promises
- Aggregate raw responses from multiple services
- Pass raw data to Layer 2 for transformation

**What it does NOT do**:
- Transform data for API response
- Build JSON structure
- Handle presentation logic

### Layer 2: JSON Response Layer

**Purpose**: Transform external data into API response format.

**Components**:
- `DataTransformers` - Convert external models to API format
- `ResponseAggregator` - Combines transformed data
- `DTOs` - Type-safe response structures

**Responsibilities**:
- Transform external models to internal representation
- Build consistent JSON response structure
- Apply business rules for presentation
- Ensure type safety in responses

**What it does NOT do**:
- Make HTTP requests (this belongs to Layer 1)
- Access external services directly
- Handle caching or network concerns

---

## 3. Separation of Concerns

### Correct Separation

```
Layer 1 (HTTP Reading)        Layer 2 (JSON Response)
─────────────────────         ──────────────────────
EditorialOrchestrator         DataTransformers
  │                              │
  ├── EditorialFetcher          ├── BodyDataTransformer
  │   └── QueryEditorialClient  │   └── ParagraphDataTransformer
  │                              │   └── SubHeadDataTransformer
  ├── EmbeddedContentFetcher    │
  │   └── QueryMultimediaClient ├── MultimediaDataTransformer
  │                              │
  └── PromiseResolver           └── ResponseAggregator
      └── Utils::settle()            └── Combines all
```

### Design Decision: No HTTP in Response Layer

**Rule**: DataTransformers MUST NOT make HTTP requests.

**Rationale**:
1. **Single Responsibility** - Transformers only transform
2. **Testability** - Transformers can be unit tested without mocking HTTP
3. **Performance** - All HTTP calls happen in parallel in Layer 1
4. **Maintainability** - Clear separation makes debugging easier

**Current Status**: ✅ COMPLIANT
- DataTransformers only receive already-fetched data
- HTTP calls are isolated in Orchestrators and Fetchers
- Thumbor service only builds URLs (no HTTP requests)
