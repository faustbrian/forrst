---
title: "Issue 2: Batch"
---

# Issue 2: Batch

> ✅ **FINAL DECISION:** Remove batch extension. Forrst is a single-request protocol.

---

## Decision

**Remove the batch extension entirely.**

- Forrst is a single-request protocol
- Clients handle orchestration (Laravel Bus::batch, etc.)
- Atomic operations belong in domain functions
- HTTP/2 multiplexing handles concurrent requests

---

## Original Problem

The specification contains contradictory messaging about batch operations:

### FAQ States:
> "No batch requests — Make concurrent HTTP/2 requests instead. See FAQ for rationale."

### Yet:
A full Batch extension (`urn:forrst:ext:batch`) exists with:
- Atomic and independent modes
- Operation arrays
- Transaction semantics
- Error aggregation

### The Confusion

Developers reading the FAQ think batching is discouraged. Developers reading extensions find a full batch implementation. Which is correct?

### Why This Matters

1. **Implementation Uncertainty**: Should servers implement batch? Is it core or optional?
2. **Client Confusion**: Should clients batch or use HTTP/2 multiplexing?
3. **Documentation Trust**: Contradictions erode spec credibility
4. **HTTP Status Complexity**: Batch complicates HTTP status codes (see Issue 1)

---

## Analysis

### The Laravel Bus Pattern

Client-side orchestration (like Laravel's Bus) handles batching elegantly:

```php
$batch = Bus::batch([
    new ImportCsv(1, 100),
    new ImportCsv(101, 200),
    new ImportCsv(201, 300),
])->before(function (Batch $batch) {
    // Batch created...
})->progress(function (Batch $batch) {
    // Single job completed...
})->then(function (Batch $batch) {
    // All jobs completed successfully...
})->catch(function (Batch $batch, Throwable $e) {
    // First failure detected...
})->finally(function (Batch $batch) {
    // Batch finished...
})->dispatch();
```

This provides:
- Progress callbacks
- Partial failure handling
- Chaining and nesting
- No protocol complexity

### Why Protocol-Level Batch is Problematic

1. **HTTP Status Ambiguity**: Mixed results (8 pass, 2 fail) → what status code?
2. **Error Handling Complexity**: Which errors bubble up? How to aggregate?
3. **Atomicity Assumptions**: Not all backends support transactions
4. **Timeout Coordination**: Batch timeout vs individual operation timeouts
5. **Retry Semantics**: Retry the batch or individual operations?

### When HTTP/2 Multiplexing Works

- Independent operations with no transaction requirements
- Operations against different resources
- When you want individual timeouts/retries per request
- Client controls concurrency

### When "Atomic Batch" is Needed

- **Atomic transactions**: Debit account A, credit account B

But this should be a **domain function**, not protocol feature:

```json
{
  "call": {
    "function": "ledger.transfer",
    "version": "1.0.0",
    "arguments": {
      "from_account": "A",
      "to_account": "B",
      "amount": 100
    }
  }
}
```

The atomicity is the *function's* responsibility, not the protocol's.

---

## Proposed Solutions

### Option A: Remove Batch Extension (Recommended)

Keep Forrst as a single-request protocol. Clients handle orchestration.

**Rationale:**
1. HTTP/2 multiplexing handles concurrent requests efficiently
2. Batch semantics (partial failure, ordering) add complexity
3. Atomic transactions should be explicit domain functions
4. Client-side orchestration (Laravel Bus, etc.) is more flexible
5. Simplifies HTTP status code handling (Issue 1)

**Updated FAQ:**

```markdown
## Why doesn't Forrst support batch requests?

Forrst is a single-request protocol by design:

1. **HTTP/2 multiplexing** handles concurrent requests efficiently
2. **Atomic operations** belong in domain functions, not protocol
3. **Client orchestration** (queues, job batches) provides better control
4. **Simpler semantics** — no mixed-result status codes

### For concurrent operations:
Use HTTP/2 multiplexing — send multiple requests in parallel.

### For atomic operations:
Create domain functions that encapsulate the transaction:

```json
// Instead of batching debit + credit:
{
  "call": {
    "function": "ledger.transfer",
    "version": "1.0.0",
    "arguments": {
      "from": "account_a",
      "to": "account_b",
      "amount": 100
    }
  }
}
```

### For complex workflows:
Use client-side orchestration with progress tracking:

```php
Bus::batch([
    new ProcessOrder($orderId),
    new SendConfirmation($orderId),
])->then(fn() => /* all done */)
  ->catch(fn($e) => /* handle failure */)
  ->dispatch();
```
```

### Option B: Keep Batch, Accept Complexity

If batch stays, accept the implications:
- Use 207 Multi-Status for mixed results
- Document complex error handling
- Accept the contradiction with "no batch" FAQ

**Not recommended** — adds complexity without clear benefit.

---

## Final Recommendation

**Remove batch extension.**

### Actions Required

1. Delete `extensions/batch.md`
2. Update FAQ with clear rationale
3. Remove batch error codes from `errors.md` (`BATCH_FAILED`, `BATCH_TOO_LARGE`, `BATCH_TIMEOUT`)
4. Update index.md to remove batch from extension list

### What Clients Use Instead

| Need | Solution |
|------|----------|
| Atomic multi-operation | Domain function (e.g., `ledger.transfer`) |
| Bulk create/update | Domain function (e.g., `users.bulk_create`) |
| Progress tracking | Client-side Bus/batch with callbacks |
| Ordered execution | Client-side chaining |
| Concurrent requests | HTTP/2 multiplexing |

### What Protocol Gains

| Benefit | Impact |
|---------|--------|
| Simpler HTTP status | Clear 1:1 error mapping (Issue 1) |
| Cleaner spec | No batch edge cases |
| Consistent semantics | Every request is single-operation |
| Easier implementation | Servers don't need batch logic |

---

## Updated FAQ Text

```markdown
## Why doesn't Forrst support batch requests?

Forrst is a single-request protocol by design:

1. **HTTP/2 multiplexing** handles concurrent requests efficiently
2. **Atomic operations** belong in domain functions, not protocol
3. **Client orchestration** (queues, job batches) provides better control
4. **Simpler semantics** — one request, one response, one status code

### For concurrent operations

Use HTTP/2 multiplexing — send multiple requests in parallel.

### For atomic operations

Create domain functions that encapsulate the transaction:

```json
{
  "call": {
    "function": "ledger.transfer",
    "version": "1.0.0",
    "arguments": {
      "from": "account_a",
      "to": "account_b",
      "amount": 100
    }
  }
}
```

### For complex workflows

Use client-side orchestration with progress tracking:

```php
Bus::batch([
    new ProcessOrder($orderId),
    new SendConfirmation($orderId),
])->then(fn() => /* all done */)
  ->catch(fn($e) => /* handle failure */)
  ->dispatch();
```
```
