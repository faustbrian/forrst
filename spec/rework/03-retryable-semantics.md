---
title: "Issue 3: Retry Semantics"
---

# Issue 3: Retry Semantics

> ✅ **FINAL DECISION:** Move retry to extension (`urn:forrst:ext:retry`)

---

## Decision

**Remove `retryable: boolean` from error objects. Create a dedicated retry extension.**

```json
{
  "errors": [{
    "code": "RATE_LIMITED",
    "message": "Too many requests"
  }],
  "extensions": [{
    "urn": "urn:forrst:ext:retry",
    "data": {
      "allowed": true,
      "after": { "value": 5, "unit": "second" },
      "strategy": "fixed",
      "max_attempts": 3
    }
  }]
}
```

---

## Original Problem

The current spec uses a boolean `retryable` field:

```json
{
  "code": "RATE_LIMITED",
  "message": "Too many requests",
  "retryable": true
}
```

### Why This is Insufficient

Different retryable errors require different retry strategies:

| Error | Retry Strategy |
|-------|----------------|
| `RATE_LIMITED` | Wait for `retry_after`, then retry once |
| `UNAVAILABLE` | Exponential backoff with jitter |
| `DEADLINE_EXCEEDED` | Retry with longer deadline |
| `IDEMPOTENCY_PROCESSING` | Wait briefly, retry with same idempotency key |
| `INTERNAL_ERROR` | Exponential backoff, limited attempts |
| `DEPENDENCY_ERROR` | Retry after dependency recovers |

A boolean can't express:
- **When** to retry (immediately? after delay?)
- **How** to retry (same request? modified?)
- **How many times** to retry
- **What strategy** to use (linear, exponential, fixed?)

### Current Workaround

The spec uses `details.retry_after` for timing:

```json
{
  "code": "RATE_LIMITED",
  "retryable": true,
  "details": {
    "retry_after": { "value": 5, "unit": "second" }
  }
}
```

But this is:
- Not standardized (field name varies)
- Not always present
- Doesn't cover strategy

---

## Proposed Solutions

### Option A: Structured Retry Object (Recommended)

Replace `retryable: boolean` with a `retry` object:

```json
{
  "code": "RATE_LIMITED",
  "message": "Too many requests",
  "retry": {
    "allowed": true,
    "after": { "value": 5, "unit": "second" },
    "strategy": "fixed",
    "max_attempts": 1
  }
}
```

**Retry Object Schema:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `allowed` | boolean | Yes | Whether retry is permitted |
| `after` | duration | No | Minimum wait before retry |
| `strategy` | string | No | `"fixed"`, `"exponential"`, `"immediate"` |
| `max_attempts` | integer | No | Suggested maximum retry attempts |

**Strategy Values:**

| Strategy | Behavior |
|----------|----------|
| `immediate` | Retry without delay |
| `fixed` | Wait `after` duration, retry |
| `exponential` | Exponential backoff starting from `after` |

**Benefits:**
- All retry information in one place
- Self-documenting
- Extensible

**Drawbacks:**
- Breaking change from `retryable: boolean`
- More verbose

### Option B: Keep Boolean, Standardize Details

Keep `retryable` but standardize retry-related `details` fields:

```json
{
  "code": "RATE_LIMITED",
  "retryable": true,
  "details": {
    "retry_after": { "value": 5, "unit": "second" },
    "retry_strategy": "fixed",
    "retry_max_attempts": 1
  }
}
```

**Benefits:**
- Backwards compatible
- Gradual adoption

**Drawbacks:**
- Scattered information
- `retryable: true` without retry details is ambiguous
- Namespace pollution in `details`

### Option C: Error Code Implies Strategy

Define retry strategy per error code in the spec:

```markdown
| Code | Retryable | Default Strategy |
|------|-----------|------------------|
| `RATE_LIMITED` | Yes | Fixed delay from `details.retry_after` |
| `UNAVAILABLE` | Yes | Exponential backoff, base 1s, max 32s |
| `DEADLINE_EXCEEDED` | Yes | Immediate with longer deadline |
| `INTERNAL_ERROR` | Yes | Exponential backoff, max 3 attempts |
```

**Benefits:**
- No schema change
- Clear expectations
- Simpler wire format

**Drawbacks:**
- Less flexible
- Server can't override strategy
- New error codes need spec updates

---

## Final Recommendation

**Create `urn:forrst:ext:retry` extension. Remove `retryable` from error schema.**

### Retry Extension Specification

**Extension URN:** `urn:forrst:ext:retry`

**Response Data:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `allowed` | boolean | Yes | Whether retry is permitted |
| `after` | duration | No | Minimum wait before retry |
| `strategy` | string | No | `"immediate"`, `"fixed"`, `"exponential"` |
| `max_attempts` | integer | No | Suggested maximum attempts |

**Example:**
```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [{
    "code": "RATE_LIMITED",
    "message": "Too many requests"
  }],
  "extensions": [{
    "urn": "urn:forrst:ext:retry",
    "data": {
      "allowed": true,
      "after": { "value": 5, "unit": "second" },
      "strategy": "fixed",
      "max_attempts": 3
    }
  }]
}
```

### Actions Required

1. Create `extensions/retry.md` with full specification
2. Remove `retryable` field from error schema in `errors.md`
3. Update all error examples to remove `retryable`
4. Add retry extension to extension index

### Migration Path

**Phase 1:** Support both `retryable` boolean and retry extension
**Phase 2:** Deprecate `retryable` in errors, warn in docs
**Phase 3:** Remove `retryable` from error schema in v1.0

---

## Default Retry Strategies

For errors without explicit `retry` object:

| Code | Default Strategy |
|------|------------------|
| `RATE_LIMITED` | Fixed, 60s default |
| `UNAVAILABLE` | Exponential, base 1s |
| `DEADLINE_EXCEEDED` | Immediate |
| `INTERNAL_ERROR` | Exponential, base 1s, max 3 |
| `DEPENDENCY_ERROR` | Exponential, base 2s |
| `IDEMPOTENCY_PROCESSING` | Fixed, 1s |

Clients SHOULD use these defaults when `retry.after` is not specified.
