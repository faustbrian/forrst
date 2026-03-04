---
title: Atomic Lock
description: Distributed locking for exclusive resource access
---

# Atomic Lock

> Distributed locking for exclusive resource access

**Extension URN:** `urn:forrst:ext:atomicLock`

---

## Overview

The atomic lock extension enables distributed locking to prevent concurrent access to shared resources. Unlike idempotency (which prevents duplicate processing of the same request), atomic locks block all requests targeting a locked resource until the lock is released.

---

## When to Use

Atomic locks SHOULD be used for:
- Preventing concurrent modifications to the same resource
- Serializing access to external systems with limited concurrency
- Implementing exclusive processing windows (e.g., billing reports at midnight)
- Protecting critical sections across distributed systems

Atomic locks SHOULD NOT be used for:
- Request deduplication (use idempotency instead)
- Rate limiting (use rate-limit extension instead)
- Short-lived operations where contention is unlikely

---

## Atomic Lock vs Idempotency

| Feature | Idempotency | Atomic Lock |
|---------|-------------|-------------|
| Purpose | Prevent duplicate side effects on retry | Block concurrent access to resource |
| Scope | Same request → same result | Different requests → mutually exclusive |
| Key behavior | Returns cached result | Blocks or rejects request |
| Release | Automatic after TTL | Manual or automatic |
| Cross-process | No | Yes (via owner token) |

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | Yes | Lock identifier |
| `ttl` | object | Yes | Lock duration |
| `scope` | string | No | `function` (default) or `global` |
| `block` | object | No | Wait timeout for lock acquisition |
| `owner` | string | No | Custom owner token (auto-generated if omitted) |
| `auto_release` | boolean | No | Release after function execution (default: `true`) |

### Key Format

Keys SHOULD describe the resource being locked:

| Pattern | Example |
|---------|---------|
| Entity ID | `user:123` |
| Entity + operation | `order:456:fulfillment` |
| Resource group | `billing:report` |
| Composite | `inventory:warehouse:A:sku:XYZ` |

### Scope Behavior

| Scope | Key becomes | Use case |
|-------|-------------|----------|
| `function` | `lock:{function}:{key}` | Prevent same key collision across functions |
| `global` | `lock:{key}` | Cross-function locks (e.g., billing operations) |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `key` | string | Echoed lock key |
| `acquired` | boolean | Whether lock was acquired |
| `owner` | string | Owner token for cross-process release |
| `scope` | string | Applied scope (`function` or `global`) |
| `expires_at` | string | ISO 8601 timestamp when lock expires |

---

## Behavior

### Lock Acquisition (Immediate)

When no `block` option is provided:

1. Server MUST attempt to acquire lock immediately
2. If lock is available, acquire and process request
3. If lock is held, return `LOCK_ACQUISITION_FAILED` error
4. Server MUST return owner token in response

### Lock Acquisition (Blocking)

When `block` option is provided:

1. Server MUST attempt to acquire lock
2. If lock is held, wait up to `block` duration
3. If acquired within timeout, process request
4. If timeout exceeded, return `LOCK_TIMEOUT` error

### Lock Release (Automatic)

When `auto_release` is `true` (default):

1. Lock MUST be released after function execution completes
2. Lock MUST be released even if function returns an error
3. Lock MUST be released even if function throws an exception

### Lock Release (Manual)

When `auto_release` is `false`:

1. Lock remains held after function execution
2. Client MUST call `urn:cline:forrst:ext:atomic-lock:fn:release` with key + owner
3. Lock will auto-expire at `expires_at` if not released

### Cross-Function Locking

When `scope` is `global`:

1. Lock key is NOT prefixed with function name
2. Same key blocks all functions using that key
3. Useful for coordinated operations across functions

---

## System Functions

### `urn:cline:forrst:ext:atomic-lock:fn:release`

Release a lock with ownership verification.

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | Yes | Lock key (with scope prefix if applicable) |
| `owner` | string | Yes | Owner token from acquisition |

**Result:**

```json
{
  "released": true,
  "key": "lock:payments.charge:user:123"
}
```

**Errors:**

| Code | Description |
|------|-------------|
| `LOCK_NOT_FOUND` | Lock does not exist or already expired |
| `LOCK_OWNERSHIP_MISMATCH` | Owner token does not match |

### `urn:cline:forrst:ext:atomic-lock:fn:force-release`

Force release a lock without ownership check (admin operation).

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | Yes | Lock key (with scope prefix if applicable) |

**Result:**

```json
{
  "released": true,
  "key": "lock:billing:report",
  "forced": true
}
```

**Errors:**

| Code | Description |
|------|-------------|
| `LOCK_NOT_FOUND` | Lock does not exist |

### `urn:cline:forrst:ext:atomic-lock:fn:status`

Check the status of a lock.

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | Yes | Lock key (with scope prefix if applicable) |

**Result:**

```json
{
  "key": "lock:payments.charge:user:123",
  "locked": true,
  "owner": "uuid-abc-123",
  "acquired_at": "2024-03-15T10:00:00Z",
  "expires_at": "2024-03-15T10:30:00Z",
  "ttl_remaining": 1200
}
```

When not locked:

```json
{
  "key": "lock:payments.charge:user:123",
  "locked": false
}
```

---

## Examples

### Request with Atomic Lock (Auto-release)

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "call": {
    "function": "payments.charge",
    "version": "1.0.0",
    "arguments": {
      "user_id": "user_123",
      "amount": 100
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:atomicLock",
      "options": {
        "key": "user:123",
        "ttl": { "value": 30, "unit": "second" }
      }
    }
  ]
}
```

### Successful Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "result": {
    "charge_id": "ch_abc",
    "status": "succeeded"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:atomicLock",
      "data": {
        "key": "user:123",
        "acquired": true,
        "owner": "550e8400-e29b-41d4-a716-446655440000",
        "scope": "function",
        "expires_at": "2024-03-15T10:30:30Z"
      }
    }
  ]
}
```

### Request with Blocking and Manual Release

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_002",
  "call": {
    "function": "billing.generateReport",
    "version": "1.0.0",
    "arguments": {
      "month": "2024-03"
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:atomicLock",
      "options": {
        "key": "billing:report",
        "ttl": { "value": 1, "unit": "hour" },
        "scope": "global",
        "block": { "value": 5, "unit": "second" },
        "auto_release": false
      }
    }
  ]
}
```

### Lock Acquisition Failed

When lock is held and no blocking:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_003",
  "result": null,
  "errors": [{
    "code": "LOCK_ACQUISITION_FAILED",
    "message": "Unable to acquire lock",
    "details": {
      "key": "user:123",
      "scope": "function",
      "full_key": "lock:payments.charge:user:123"
    }
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:atomicLock",
      "data": {
        "key": "user:123",
        "acquired": false
      }
    }
  ]
}
```

### Lock Timeout

When blocking times out:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_004",
  "result": null,
  "errors": [{
    "code": "LOCK_TIMEOUT",
    "message": "Lock acquisition timed out after 5 seconds",
    "details": {
      "key": "billing:report",
      "scope": "global",
      "full_key": "lock:billing:report",
      "waited": { "value": 5, "unit": "second" }
    }
  }]
}
```

### Releasing a Lock

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_release",
  "call": {
    "function": "urn:cline:forrst:ext:atomic-lock:fn:release",
    "version": "1.0.0",
    "arguments": {
      "key": "lock:billing:report",
      "owner": "550e8400-e29b-41d4-a716-446655440000"
    }
  }
}
```

### Lock Ownership Mismatch

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_release",
  "result": null,
  "errors": [{
    "code": "LOCK_OWNERSHIP_MISMATCH",
    "message": "Lock is owned by a different process",
    "details": {
      "key": "lock:billing:report"
    }
  }]
}
```

---

## Server Implementation

### Lock Storage

Servers MUST use a distributed lock-capable backend:
- Redis (recommended)
- Memcached
- DynamoDB
- Database with row locking

### Lock Key Format

```
lock:{scope_prefix}:{key}

Examples:
- lock:payments.charge:user:123  (function scope)
- lock:billing:report            (global scope)
```

### Owner Token Generation

When no custom owner is provided:
- Server MUST generate a UUID v4
- Server MUST store owner with lock
- Server MUST return owner in response

### Laravel Integration

```php
use Illuminate\Support\Facades\Cache;

// Acquisition
$lock = Cache::lock($key, $ttl);
if ($lock->get()) {
    // Acquired
    $owner = $lock->owner();
}

// Blocking acquisition
$lock->block($timeout);

// Cross-process release
Cache::restoreLock($key, $owner)->release();

// Force release
Cache::lock($key)->forceRelease();
```

---

## Error Codes

| Code | Retryable | Description |
|------|-----------|-------------|
| `LOCK_ACQUISITION_FAILED` | Yes | Lock is held, no blocking requested |
| `LOCK_TIMEOUT` | Yes | Blocking acquisition timed out |
| `LOCK_NOT_FOUND` | No | Lock does not exist for release |
| `LOCK_OWNERSHIP_MISMATCH` | No | Wrong owner token for release |
| `LOCK_ALREADY_RELEASED` | No | Lock was already released |

---

## Best Practices

### TTL Selection

- Set TTL slightly longer than expected execution time
- Account for network latency and retries
- Very long TTLs should use `auto_release: false`

### Key Design

- Use specific keys for fine-grained locking
- Use broader keys for coarse-grained exclusion
- Avoid dynamic keys that could create unbounded locks

### Error Handling

- Implement exponential backoff on `LOCK_ACQUISITION_FAILED`
- Use `block` option for expected contention
- Monitor lock timeout rates for capacity issues

### Cross-Function Coordination

```json
{
  "options": {
    "key": "billing:monthly",
    "scope": "global",
    "ttl": { "value": 1, "unit": "hour" }
  }
}
```

Multiple billing functions can use this key to ensure only one runs at a time.
