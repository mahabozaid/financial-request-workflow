# Financial Request Workflow

A production-grade **financial request management system** built with Laravel 13, demonstrating domain-driven design, event-driven architecture, distributed idempotency, and a fully containerised DevOps pipeline.

[![CI](https://github.com/mahabozaid/financial-request-workflow/actions/workflows/ci.yml/badge.svg)](https://github.com/mahabozaid/financial-request-workflow/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?logo=postgresql)
![Redis](https://img.shields.io/badge/Redis-7-DC382D?logo=redis)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker)
![Tests](https://img.shields.io/badge/Tests-46%20passing-brightgreen)

---

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [State Machine](#state-machine)
- [Idempotency Strategy](#idempotency-strategy)
- [API Reference](#api-reference)
- [Queue & Background Jobs](#queue--background-jobs)
- [Infrastructure & DevOps](#infrastructure--devops)
- [CI/CD Pipeline](#cicd-pipeline)
- [Testing](#testing)
- [Getting Started](#getting-started)
- [Cloud Cost Optimization: Self-Hosted n8n on AWS](#cloud-cost-optimization-self-hosted-n8n-on-aws)

---

## Overview

This system manages the full lifecycle of financial requests — from creation through multi-step review, ERP reconciliation, and terminal resolution — with strong correctness guarantees:

- **No duplicate processing** — two-layer idempotency (Redis lock + durable DB record)
- **No lost state transitions** — all transitions run inside database transactions
- **No silent failures** — domain exceptions propagate cleanly; the job retries for 24 h with exponential back-off before marking permanently failed
- **No tight coupling** — the domain is isolated behind interfaces; swapping the ERP provider or storage backend requires changing one binding in the service provider

---

## Architecture

```
app/
├── Domain/Financial/          # Pure domain logic — no HTTP, no framework leakage
│   ├── DTOs/                  # Immutable input objects (readonly classes)
│   ├── Enums/                 # FinancialRequestStatus with toState() + isTerminal()
│   ├── Events/                # Domain events (Approved, Completed, Failed)
│   ├── Exceptions/            # Typed exceptions (InvalidStateTransition, ERPConnection…)
│   ├── Listeners/             # Async side-effects via event bus
│   ├── Models/                # Eloquent model with enum casting + query scopes
│   ├── Repositories/          # Interface + Eloquent implementation
│   ├── Services/              # Application services (orchestration layer)
│   └── States/                # One class per status — State pattern
├── Http/
│   ├── Controllers/Api/       # Thin controllers — delegate to services
│   ├── Middleware/            # HandleIdempotency — caches responses 24 h
│   ├── Requests/              # Form requests with validation rules
│   └── Resources/             # JSON API resources
├── Jobs/
│   └── ReconcileTransactionJob.php   # Async ERP sync with retry logic
└── Providers/
    └── FinancialDomainServiceProvider.php   # All bindings + event wiring in one place
```

**Key design decisions:**

| Decision | Rationale |
|---|---|
| Amounts stored as `DECIMAL(15,4)` strings, compared with `bcmath` | Avoid IEEE 754 rounding errors in financial arithmetic |
| Repository pattern with interface | HTTP layer, services, and tests depend on the interface — not Eloquent |
| `readonly` DTOs | Input objects are immutable once validated; no partial mutation bugs |
| State pattern (one class per status) | Each status owns its allowed transitions and side-effects |
| Two-layer idempotency | Redis lock prevents concurrent races; DB record survives Redis restarts |

---

## State Machine

Every status transition is validated by `FinancialRequestStateMachine`. Illegal transitions throw `InvalidStateTransitionException` with `from`, `to`, and `requestId` properties.

```
                 ┌─────────────┐
         ┌──────►│ UnderReview │──────┐
         │       └─────────────┘      │
         │                            ▼
    ┌─────────┐               ┌──────────┐    ┌───────────┐
    │ Pending │               │ Approved │───►│ Cancelled │
    └─────────┘               └──────────┘    └───────────┘
         │                            │
         │                            ▼
         │                     ┌────────────┐      ┌───────────┐
         │                     │ Processing │─────►│ Completed │ ✓ terminal
         │                     └────────────┘      └───────────┘
         │                            │
         │                            ▼
         │                       ┌────────┐
         └──────────────────────►│ Failed │──► Processing (retry)
                                 └────────┘──► Cancelled ✓ terminal

              Rejected ✓ terminal
```

Each state class implements `FinancialRequestStateContract`:

```php
interface FinancialRequestStateContract
{
    public function getAllowedTransitions(): array;
    public function onEnter(FinancialRequest $request): void;   // fires events
    public function onExit(FinancialRequest $request): void;
    public function status(): FinancialRequestStatus;
}
```

**On-enter hooks by state:**

| State | Side Effect |
|---|---|
| `ApprovedState` | Dispatches `FinancialRequestApproved` → `InitiateReconciliation` listener → `ReconcileTransactionJob` |
| `CompletedState` | Dispatches `FinancialRequestCompleted` |
| `FailedState` | Dispatches `FinancialRequestFailed` → `HandleFailedRequest` listener |

---

## Idempotency Strategy

The ERP reconciliation flow uses two independent safety layers so retries are always safe:

```
POST /financial-requests
     │
     ▼
HandleIdempotency Middleware
     │  ┌──────────────────────────────────┐
     │  │ Cache hit?  Return 200 + header  │ ◄── X-Idempotent-Replayed: true
     │  └──────────────────────────────────┘
     │  Cache miss → continue
     ▼
FinancialRequestController::store()
     ▼
ReconcileTransactionJob dispatched
     │
     ▼
Layer 1 — Redis distributed lock (120 s TTL)
     │  Prevents two workers processing the same key in parallel
     ▼
Layer 2 — idempotency_records table row (7-day TTL)
     │  status: processing → completed | failed
     │  Survives Redis restarts; unique index is the ultimate guarantee
     ▼
ERP reconciliation call
     ▼
DB transaction:  external_reference updated + status → Completed + record marked complete
     │
 finally: lock.release()
```

---

## API Reference

Base path: `/api/v1` — Authentication via Laravel Sanctum bearer tokens.

### Auth

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/auth/login` | Obtain a Sanctum token |
| `POST` | `/auth/logout` | Revoke the current token |

### Financial Requests

| Method | Endpoint | Description | Headers |
|---|---|---|---|
| `GET` | `/financial-requests` | List (paginated) | `Authorization` |
| `POST` | `/financial-requests` | Create new request | `Authorization`, `Idempotency-Key` |
| `GET` | `/financial-requests/{id}` | Retrieve single request | `Authorization` |
| `POST` | `/financial-requests/{id}/transition` | Transition status | `Authorization` |
| `GET` | `/financial-requests/{id}/allowed-transitions` | List valid next statuses | `Authorization` |

**Create request body:**

```json
{
  "amount": "1500.00",
  "currency": "USD",
  "external_reference": "INV-2024-001",
  "metadata": { "department": "finance" }
}
```

**JSON resource shape:**

```json
{
  "id": 42,
  "amount": "1500.0000",
  "currency": "USD",
  "status": { "value": "pending", "label": "Pending" },
  "external_reference": null,
  "metadata": { "department": "finance" },
  "is_terminal": false,
  "created_at": "2024-01-15T10:00:00Z",
  "updated_at": "2024-01-15T10:00:00Z"
}
```

**Validation rules (CreateFinancialRequestRequest):**

```
amount           required | numeric | gt:0 | decimal:0,4
currency         required | string  | size:3 | alpha
external_reference  sometimes | nullable | string | max:255
metadata         sometimes | nullable | array
```

---

## Queue & Background Jobs

### ReconcileTransactionJob

Queued on the `reconciliation` Redis queue. Designed for resilience against transient ERP failures.

| Setting | Value |
|---|---|
| Queue | `reconciliation` (Redis) |
| Connection | `redis` |
| Timeout | 30 seconds |
| Max exceptions | 10 |
| Backoff sequence | 30 s → 60 s → 120 s → 300 s → 600 s |
| Retry window | 24 hours |
| On permanent failure | Transitions request to `Failed` via `failed()` hook |

**Job execution flow:**

```
1. Acquire Redis lock (120 s TTL)          — prevents parallel workers on same key
2. Check idempotency_records               — skip if already completed
3. markAsProcessing()                      — durable record in DB
4. DB transaction: status → Processing
5. Call ERPReconciliationService::reconcile()
6. DB transaction: update external_reference + status → Completed + markAsCompleted()
7. Log success
── On ERPConnectionException: markAsFailed() + re-throw (triggers retry)
── On any Throwable:          markAsFailed() + re-throw
── finally: lock.release()   — always releases, even on exception
```

---

## Infrastructure & DevOps

### Stack

| Service | Image | Role |
|---|---|---|
| **app** | Custom PHP 8.4-FPM | Application runtime |
| **nginx** | nginx:1.27-alpine | Reverse proxy, static files, TLS termination |
| **postgres** | postgres:16-alpine | Primary database (persistent volume) |
| **redis** | redis:7-alpine | Queue, cache, sessions (512 MB, AOF persistence) |
| **queue-worker** | Same as app | Consumes `reconciliation` + `default` queues |
| **pgadmin** | pgadmin4 | Database management UI (optional profile) |

### Docker Architecture

```
┌─────────────────────────────────────────────────────────┐
│                     financial_net (bridge)               │
│                                                         │
│   ┌──────────┐    ┌─────────────┐    ┌──────────────┐  │
│   │  nginx   │───►│  app (FPM)  │───►│  PostgreSQL  │  │
│   │ :80/:443 │    │   :9000     │    │    :5432     │  │
│   └──────────┘    └─────────────┘    └──────────────┘  │
│                          │                              │
│                          ▼                              │
│                   ┌──────────────┐                      │
│                   │    Redis     │                      │
│                   │    :6379     │                      │
│                   └──────────────┘                      │
│                          ▲                              │
│                   ┌──────────────┐                      │
│                   │ queue-worker │                      │
│                   │ (1 replica)  │                      │
│                   └──────────────┘                      │
└─────────────────────────────────────────────────────────┘
```

### Multi-Stage Dockerfile

```
Stage 1 — composer   Install PHP dependencies (no dev tools in final image)
Stage 2 — base       Shared PHP extensions: bcmath, mbstring, opcache,
                     pdo_pgsql, zip, pcntl, redis
Stage 3 — production Hardened: disabled exec/passthru/shell_exec,
                     opcache preload, non-root user
Stage 4 — development Adds Xdebug, relaxed ini
```

### Resource Limits (docker-compose)

| Container | CPU | Memory |
|---|---|---|
| app | 1.0 | 512 MB |
| queue-worker | 0.5 | 256 MB |
| redis | 0.5 | 512 MB (maxmemory) |
| postgres | 1.0 | 512 MB |

### Nginx Configuration

- Gzip compression for all text content
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy`, `Referrer-Policy`
- Health check endpoint at `/_health` (no logging)
- Static asset caching (1 year `Cache-Control: immutable`)

---

## CI/CD Pipeline

GitHub Actions workflow triggers on every push to `master` and every pull request targeting `master`.

```yaml
Trigger: push / pull_request → master

Job: PHPUnit / Pest (ubuntu-latest)
  1. actions/checkout@v4
  2. shivammathur/setup-php@v2
       php-version: "8.4"
       extensions: pdo, pdo_sqlite, sqlite3, redis, bcmath, zip, mbstring, opcache
  3. actions/cache@v4  (keyed on composer.lock hash)
  4. composer install --no-interaction --prefer-dist --optimize-autoloader
  5. php artisan key:generate --force
  6. php artisan test --parallel
```

No external services are needed in CI — the test suite uses an **in-memory SQLite database** and fakes for queues, events, and cache. Tests run in parallel for maximum speed.

---

## Testing

**Framework:** Pest 4.7 with Laravel plugin

**Test database:** In-memory SQLite (`:memory:`) — no setup, no teardown, no flakiness

```
Tests:  46 passed (65 assertions)
```

### Test breakdown

| Suite | File | Coverage |
|---|---|---|
| Unit | `StateMachineTest` | 22 tests — valid/invalid transition matrix, exception properties, terminal states, metadata merging |
| Unit | `IdempotencyServiceTest` | 4 tests — lock acquisition, hasBeenProcessed, markAsCompleted |
| Feature | `FinancialRequestTest` | 10 tests — CRUD, validation, state transitions, event dispatch, job queuing, rollback |
| Feature | `ReconcileTransactionJobTest` | 8 tests — processing, idempotency skip, ERP failure, `failed()` hook, lock release in `finally`, backoff/retry config |

### Running tests

```bash
# In Docker — zero local setup
make test-docker

# Locally (requires pdo_sqlite, bcmath, mbstring)
make test

# Unit tests only
make test-unit

# Feature tests only
make test-feature

# Filter by name
make test-filter q="state machine"

# Parallel (faster on multi-core)
make test-parallel
```

---

## Getting Started

### Prerequisites

- Docker 24+
- Docker Compose v2+

### Start the full stack

```bash
git clone https://github.com/mahabozaid/financial-request-workflow.git
cd financial-request-workflow

cp .env.example .env          # fill in DB_PASSWORD, REDIS_PASSWORD, ERP_* values
docker compose up -d

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### Verify services

```bash
# Application
curl http://localhost/api/v1/health

# Queue worker processing
docker logs financial-app-queue-worker-1 -f

# pgAdmin (optional)
docker compose --profile monitoring up -d
open http://localhost:5050
```

### Run tests inside Docker

```bash
make test-docker
```

---

---

## Cloud Cost Optimization: Self-Hosted n8n on AWS

Instead of a $50+/month n8n cloud subscription, the same automation engine runs self-hosted on AWS for under $7/month total.

### EC2 Instance

| Setting | Choice | Reason |
|---|---|---|
| Type | **t4g.micro** (ARM Graviton2) | Cheapest general-purpose instance with enough RAM for n8n |
| vCPU / RAM | 2 vCPU / 1 GB | Sufficient for low-to-medium workflow load |
| On-demand price | ~$6.05 / month | $0.0084/hr × 730 h |
| OS | Amazon Linux 2023 (ARM) | Free, minimal footprint, security-patched |
| Storage | 8 GB **gp3** EBS | $0.64/month — 20 % cheaper than gp2, better baseline IOPS |

**Estimated monthly bill: ~$6.70** (instance + storage, no RDS, no NAT Gateway, no load balancer).

### Docker Deployment

Single `docker-compose.yml` on the instance — no orchestrator overhead:

```yaml
services:
  n8n:
    image: n8nio/n8n:latest
    restart: unless-stopped
    ports:
      - "127.0.0.1:5678:5678"   # only localhost — Caddy terminates TLS
    environment:
      - N8N_HOST=${DOMAIN}
      - N8N_PROTOCOL=https
      - WEBHOOK_URL=https://${DOMAIN}/
      - DB_TYPE=sqlite             # no RDS — saves $15+/month
      - N8N_ENCRYPTION_KEY=${KEY}
    volumes:
      - n8n_data:/home/node/.n8n

  caddy:
    image: caddy:2-alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data

volumes:
  n8n_data:
  caddy_data:
```

**Caddyfile** (free automatic TLS via Let's Encrypt):

```
yourdomain.com {
    reverse_proxy n8n:5678
}
```

```bash
# Deploy in one command
docker compose up -d

# Upgrade with zero-config
docker compose pull && docker compose up -d
```

### Cost Optimization Strategies

| Strategy | Saving |
|---|---|
| **t4g over t3** — ARM Graviton2 is 20 % cheaper per vCPU | ~$1.50/month |
| **SQLite instead of RDS** — n8n works fine on SQLite for single-instance use | ~$15/month |
| **Caddy instead of ALB** — free TLS termination on the same host | ~$16/month |
| **gp3 over gp2** — same IOPS at lower cost | ~$0.15/month |
| **Public subnet, no NAT Gateway** — n8n only needs outbound internet for webhooks | ~$32/month |
| **1-year Reserved Instance** — if usage is confirmed stable | further 30 % off EC2 |

### Cloud Waste Reduction Policies

```
1. Billing alert   — AWS Budget alarm at $8 sends email before limit is hit
2. Instance scheduler — stop the instance nights + weekends if workflows are
                        business-hours only (saves up to 65 % of EC2 cost)
3. Lifecycle policy — EBS snapshots kept for 7 days only, auto-deleted after
4. Resource tagging — all resources tagged Project=n8n, Env=prod for
                      Cost Explorer drill-down
5. CloudWatch Logs  — retention set to 7 days (default is forever = $$$)
6. Elastic IP       — released immediately if instance is terminated to avoid
                      the $0.005/hr idle charge
```

### Cost Summary

| Resource | Monthly Cost |
|---|---|
| t4g.micro on-demand | $6.05 |
| 8 GB gp3 EBS | $0.64 |
| Data transfer (< 1 GB out) | ~$0.09 |
| CloudWatch metrics (basic) | $0.00 |
| **Total** | **≈ $6.78** |

---

## License

© 2026 Mahmoud Abozaid. All rights reserved.
