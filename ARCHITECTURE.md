# Financial Request System — Architecture & Design Decisions

## 1. Folder Structure

```
app/
├── Domain/
│   └── Financial/
│       ├── DTOs/
│       │   ├── CreateFinancialRequestData.php
│       │   ├── ReconcileTransactionData.php
│       │   └── TransitionFinancialRequestData.php
│       ├── Enums/
│       │   └── FinancialRequestStatus.php
│       ├── Events/
│       │   ├── FinancialRequestApproved.php
│       │   ├── FinancialRequestCompleted.php
│       │   ├── FinancialRequestFailed.php
│       │   └── FinancialRequestStateChanged.php
│       ├── Exceptions/
│       │   ├── DuplicateTransactionException.php
│       │   ├── ERPConnectionException.php
│       │   ├── IdempotencyKeyExistsException.php
│       │   └── InvalidStateTransitionException.php
│       ├── Listeners/
│       │   ├── HandleFailedRequest.php
│       │   ├── InitiateReconciliation.php
│       │   └── NotifyOnApproval.php
│       ├── Models/
│       │   └── FinancialRequest.php
│       ├── Repositories/
│       │   ├── Contracts/
│       │   │   └── FinancialRequestRepositoryInterface.php
│       │   └── EloquentFinancialRequestRepository.php
│       ├── Services/
│       │   ├── ERPReconciliationService.php
│       │   ├── FinancialRequestService.php
│       │   ├── FinancialRequestStateService.php
│       │   └── IdempotencyService.php
│       └── States/
│           ├── Contracts/
│           │   └── FinancialRequestStateContract.php
│           ├── AbstractFinancialRequestState.php
│           ├── ApprovedState.php
│           ├── CancelledState.php
│           ├── CompletedState.php
│           ├── FailedState.php
│           ├── FinancialRequestStateMachine.php
│           ├── PendingState.php
│           ├── ProcessingState.php
│           ├── RejectedState.php
│           └── UnderReviewState.php
├── Http/
│   ├── Controllers/Api/
│   │   └── FinancialRequestController.php
│   ├── Requests/
│   │   ├── CreateFinancialRequestRequest.php
│   │   └── TransitionFinancialRequestRequest.php
│   └── Resources/
│       └── FinancialRequestResource.php
├── Jobs/
│   └── ReconcileTransactionJob.php
└── Providers/
    └── FinancialDomainServiceProvider.php
```

---

## 2. State Machine Design

### Transition Map

```
Pending ──────────┬──▶ UnderReview
                  └──▶ Cancelled

UnderReview ──────┬──▶ Approved
                  └──▶ Rejected ◀── (Terminal)

Approved ─────────┬──▶ Processing
                  └──▶ Cancelled ◀── (Terminal)

Processing ───────┬──▶ Completed ◀── (Terminal)
                  └──▶ Failed

Failed ────────────┬──▶ Processing  (manual retry path)
                   └──▶ Cancelled ◀── (Terminal)
```

### Why a custom State Pattern (not spatie/laravel-model-states)?

| Concern | spatie/laravel-model-states | Custom implementation |
|---|---|---|
| External dependency | Yes (version coupling) | No |
| Framework coupling | Tight | Loose (pure PHP objects) |
| Testability | Good | Excellent (pure unit tests) |
| onEnter/onExit hooks | Via abstract methods | First-class interface methods |
| OCP compliance | Reasonable | Strict — StateMachine never changes |

The custom implementation keeps **state knowledge inside the state objects**. Adding a new state (e.g., `AwaitingDocuments`) requires:
1. A new class extending `AbstractFinancialRequestState`
2. A new enum case in `FinancialRequestStatus`
3. Updating `toState()` in the enum

`FinancialRequestStateMachine` itself **never changes** — it only calls the contract interface.

---

## 3. Idempotency — Why This Implementation Is Safe

### Problem
A queued job can be retried by Laravel (network issue, worker restart, timeout).
Without idempotency, each retry could create a duplicate ERP transaction.

### Two-Layer Defense

```
Request arrives
      │
      ▼
┌─────────────────────────────────────┐
│ Layer 1: Redis Distributed Lock     │  ← Blocks concurrent workers
│  Cache::lock("idempotency:{key}")   │    for the same key.
│  TTL = 120s (> max ERP timeout)     │    Second worker exits cleanly.
└─────────────────────────────────────┘
      │ lock acquired
      ▼
┌─────────────────────────────────────┐
│ Layer 2: DB idempotency_records     │  ← Survives Redis flush/restart.
│  SELECT WHERE key AND status=done   │    If "completed", skip and return.
│  Unique index on idempotency_key    │    DB constraint = ultimate guarantee.
└─────────────────────────────────────┘
      │ not yet processed
      ▼
   ERP call → mark completed (atomic DB update)
```

### Why This Combination Is Safe

| Scenario | Layer 1 (Redis lock) | Layer 2 (DB record) | Result |
|---|---|---|---|
| Two workers start concurrently | Worker B cannot acquire lock | — | Worker B skips |
| Worker A crashes mid-job, B retries | Lock expired; B acquires | Record is "processing" not "completed" | B re-runs (correct) |
| Redis is flushed | Lock not found (acquired immediately) | DB record found as "completed" | B skips |
| ERP call succeeds, DB write fails | Lock still held | Transaction rolls back | B retries, ERP call is idempotent via key |

The `idempotency_key` column on `financial_requests` has a `UNIQUE` index — the database itself is the final safety net even if application code has a bug.

---

## 4. Database Transaction Boundaries

```php
// FinancialRequestService::create()
DB::transaction(function() {
    // Check idempotency key (shared lock in a real app)
    // INSERT financial_requests (unique constraint enforces safety)
});

// FinancialRequestStateService::transition()
DB::transaction(function() {
    // UPDATE status + metadata atomically
    // StateMachine::transition() calls model->save() inside this transaction
    // If event dispatch throws → full rollback, status unchanged
});

// ReconcileTransactionJob::handle()
// Two separate transactions (intentional):
DB::transaction(fn() => /* transition to Processing */);
// ERP call here — OUTSIDE any transaction (avoid long-held DB locks)
DB::transaction(fn() => /* save ERP reference + transition to Completed + markAsCompleted */);
```

The ERP call is deliberately **outside** a transaction to avoid holding a DB connection open during an external HTTP call (which can take up to 15 seconds). The two-phase DB write is safe because:
- Phase 1 sets status to `Processing` — idempotent.
- Phase 2 is atomic — if it fails, the job retries and phase 1 is a no-op (already Processing).

---

## 5. Queue Configuration

### Why Redis for Queues

- **Sub-millisecond enqueue/dequeue** — no polling overhead vs. database queues.
- **Atomic operations** — `BLPOP`/`LPUSH` give race-free job assignment.
- **Native TTL support** — delayed jobs, backoff windows, retry scheduling are first-class.
- **Laravel Horizon** — visibility dashboard, throughput metrics, failed job inspection — requires Redis.
- **Horizontal scaling** — multiple workers consume from the same Redis list; zero coordination needed.

### Queue Worker Scaling

```
Increase docker-compose deploy.replicas, or on AWS ECS:
  - Desired count: 3–20 tasks per ECS service
  - Target tracking policy: SQS ApproximateNumberOfMessages > 100
  - Scale in cooldown: 300s (avoid thrashing during bursts)
```

The `reconciliation` queue is isolated from `default` so financial reconciliation jobs are never starved by lower-priority work.

---

## 6. AWS Deployment Architecture

```
Internet
   │
   ▼
AWS ALB (HTTPS, WAF)
   │
   ├── ECS Service: Laravel App (PHP-FPM + Nginx)
   │      Auto Scaling Group (CPU > 70%) → 2–20 tasks
   │      Task: 0.5 vCPU, 1 GB RAM
   │
   ├── ECS Service: Queue Workers (PHP CLI)
   │      Auto Scaling Group based on Redis queue depth → 3–30 tasks
   │      Task: 0.25 vCPU, 256 MB RAM
   │
   ├── RDS Aurora PostgreSQL (Multi-AZ, db.r6g.large)
   │      Read replica for reporting queries
   │
   └── ElastiCache Redis (Cluster mode, cache.r6g.large)
          3 shards, 1 replica each → 6 nodes total
```

### ECS vs EKS vs EC2

| Option | Best for | Tradeoff |
|---|---|---|
| **ECS (Fargate)** | This system ✓ | Simple ops; auto-scaled; no cluster management |
| EKS | Large teams, service mesh, fine-grained scheduling | High ops overhead; overkill for <10 services |
| EC2 | Legacy or cost-optimized workloads | Manual patching; harder scaling |

### SQS as Redis Alternative

SQS is **not** recommended here because:
- No native delayed-job support (requires SQS delay attribute, max 15 min — our backoff reaches 600s fine with Redis)
- No Horizon support
- Higher latency per job (~20ms vs ~1ms Redis)
- No distributed locks (would lose idempotency Layer 1)

SQS would be appropriate for fire-and-forget event notifications (SNS→SQS fan-out).

### Failure Recovery

- **Dead letter queue**: Jobs that exhaust all retries land in a `failed_jobs` table (and can be forwarded to an SQS DLQ for alerting).
- **Circuit breaker**: ERPReconciliationService can integrate with a circuit-breaker (e.g., `resilience4php`) to fail fast when ERP is down rather than exhausting all retries.
- **CloudWatch alarms**: Alert on `failed_jobs` count > 0 and Redis queue depth > 500.
- **RDS Multi-AZ**: Automatic failover in ~30s for PostgreSQL.
- **Redis persistence**: `appendonly yes` + `appendfsync everysec` ensures at-most-one-second data loss on crash.

---

## 7. SOLID Principles Applied

| Principle | Where Applied |
|---|---|
| **S**ingle Responsibility | Each service has one reason to change. `FinancialRequestService` creates; `FinancialRequestStateService` transitions; `ERPReconciliationService` talks to ERP; `IdempotencyService` guards duplicates. |
| **O**pen/Closed | `FinancialRequestStateMachine` never changes when new states are added. New states implement the contract. |
| **L**iskov Substitution | Any `FinancialRequestStateContract` implementation is substitutable — tests can inject stub states. |
| **I**nterface Segregation | `FinancialRequestRepositoryInterface` exposes only what domain services need; no persistence leakage into callers. |
| **D**ependency Inversion | Services depend on interfaces (`FinancialRequestRepositoryInterface`, `ERPReconciliationService` injected), not concrete classes. |

---

## 8. Testing Strategy

| Layer | Tool | What is tested |
|---|---|---|
| Unit | Pest | State machine transitions, DTO validation, enum behavior |
| Unit | Pest + Mockery | IdempotencyService DB/Cache calls, ERPReconciliationService HTTP logic |
| Feature | Pest + RefreshDatabase | Full create/transition flow against real SQLite, event assertions |
| Job | Pest + Queue::fake | Retry behavior, lock acquisition, failed() hook, backoff sequence |
| Contract | Pest | All 56 valid/invalid state transition combinations via datasets |
