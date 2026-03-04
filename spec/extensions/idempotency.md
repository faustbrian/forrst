---
title: Idempotency
description: Request deduplication for safe retries
---

# Idempotency

> Request deduplication for safe retries

**Extension URN:** `urn:forrst:ext:idempotency`

---

## Overview

The idempotency extension ensures that retrying a request doesn't cause duplicate side effects, following [draft-ietf-httpapi-idempotency-key-header](https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/) (The Idempotency-Key HTTP Header Field). Servers cache results keyed by the idempotency key and return cached results for duplicate requests.

---

## When to Use

Idempotency SHOULD be used for:
- Payment processing
- Order creation
- Resource provisioning
- Any operation with side effects
- Network-unreliable environments

Idempotency is NOT needed for:
- Read operations (naturally idempotent)
- Operations without side effects

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | Yes | Unique key for deduplication |
| `ttl` | object | No | Requested cache duration |

### Key Format

Keys SHOULD include enough context to be unique per logical operation:

| Pattern | Example |
|---------|---------|
| Entity + timestamp | `order_cust42_1702500000` |
| Entity + operation | `charge_order123_attempt1` |
| Combined entities | `reserve_sku123_order456` |
| UUID | `550e8400-e29b-41d4-a716-446655440000` |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `key` | string | Echoed idempotency key |
| `status` | string | `processed`, `cached`, or `processing` |
| `original_request_id` | string | Request ID that first processed this key |
| `cached_at` | string | When result was cached (if replayed) |
| `expires_at` | string | When cached result expires |

---

## Behavior

### First Request

When a new idempotency key is received:

1. Server MUST hash key with function + version
2. Server MUST process the request normally
3. Server MUST cache the result
4. Server MUST return `status: "processed"`

### Duplicate Request (Cached)

When the same idempotency key is received again:

1. Server MUST NOT reprocess the request
2. Server MUST return the cached result
3. Server MUST return `status: "cached"`
4. Server MUST include `original_request_id`

### Concurrent Request (Processing)

When duplicate key received while original is processing:

1. Server MUST NOT start new processing
2. Server MUST return `IDEMPOTENCY_PROCESSING` error
3. Client SHOULD retry after suggested delay

### Conflict Detection

When same key is used with different arguments:

1. Server MUST NOT process the request
2. Server MUST return `IDEMPOTENCY_CONFLICT` error
3. Server SHOULD include hash of original arguments

---

## Examples

### Request with Idempotency

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "call": {
    "function": "payments.charge",
    "version": "1.0.0",
    "arguments": {
      "amount": 100,
      "currency": "USD",
      "customer_id": "cust_123"
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:idempotency",
      "options": {
        "key": "charge_order456_v1"
      }
    }
  ]
}
```

### First Response (Processed)

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
      "urn": "urn:forrst:ext:idempotency",
      "data": {
        "key": "charge_order456_v1",
        "status": "processed",
        "original_request_id": "req_001",
        "expires_at": "2024-03-16T10:30:00Z"
      }
    }
  ]
}
```

### Retry Response (Cached)

Same idempotency key, different request ID:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_002",
  "result": {
    "charge_id": "ch_abc",
    "status": "succeeded"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:idempotency",
      "data": {
        "key": "charge_order456_v1",
        "status": "cached",
        "original_request_id": "req_001",
        "cached_at": "2024-03-15T10:30:00Z",
        "expires_at": "2024-03-16T10:30:00Z"
      }
    }
  ]
}
```

### Conflict Error

Same key with different arguments:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_003",
  "result": null,
  "errors": [{
    "code": "IDEMPOTENCY_CONFLICT",
    "message": "Idempotency key already used with different arguments",
    "details": {
      "key": "charge_order456_v1",
      "original_arguments_hash": "sha256:abc123..."
    }
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:idempotency",
      "data": {
        "key": "charge_order456_v1",
        "status": "conflict",
        "original_request_id": "req_001"
      }
    }
  ]
}
```

### Processing Error

Concurrent request while original is processing:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_004",
  "result": null,
  "errors": [{
    "code": "IDEMPOTENCY_PROCESSING",
    "message": "Previous request with this key is still processing",
    "details": {
      "key": "charge_order456_v1",
      "retry_after": { "value": 1, "unit": "second" }
    }
  }]
}
```

---

## ID vs Idempotency Key

These serve different purposes:

| Field | Scope | Purpose |
|-------|-------|---------|
| `id` | Per-attempt | Correlate request↔response |
| `key` | Per-operation | Deduplicate retries |

**Three retries of the same operation:**

```
Attempt 1: id="req_001", key="idem_abc" → Server processes
Attempt 2: id="req_002", key="idem_abc" → Server returns cached
Attempt 3: id="req_003", key="idem_abc" → Server returns cached
```

Different `id` values enable logging/tracing of each attempt.
Same `key` prevents duplicate processing.

---

## Server Implementation

### Storage Requirements

Servers MUST:
- Store results keyed by hash(key + function + version)
- Include request arguments hash for conflict detection
- Set TTL on cached entries (recommended: 24 hours)

### Recommended Schema

```
idempotency_cache {
  key_hash: string (primary)
  function: string
  version: string
  arguments_hash: string
  result: json
  status: enum(processing, completed, failed)
  created_at: timestamp
  expires_at: timestamp
}
```

---

## Error Codes

| Code | Retryable | Description |
|------|-----------|-------------|
| `IDEMPOTENCY_CONFLICT` | No | Key reused with different arguments |
| `IDEMPOTENCY_PROCESSING` | Yes | Previous request still processing |
