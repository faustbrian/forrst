---
title: FAQ
description: Frequently asked questions and design decisions
---

# FAQ

> Frequently asked questions and design decisions

---

## Why doesn't Forrst support notifications?

Many RPC protocols (like JSON-RPC 2.0) support "notifications" — requests without an `id` field where the server processes the request but never sends a response. Forrst intentionally omits this feature.

### The Problem with Notifications

Notifications provide no feedback. The caller cannot know:

- Did the function exist?
- Were the arguments valid?
- Did processing succeed or fail?
- Was the request even received?

For **internal microservice communication**, this lack of feedback creates reliability problems:

```json
// A notification to get a user makes no sense
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": { "id": 42 }
  }
}
// Where does the user data go? Nowhere.
```

The protocol would allow structurally valid requests that are semantically nonsensical.

### Better Alternatives

**For "fire and forget" with tracking:**

Use the [async extension](extensions/async.md). The server returns immediately with an operation ID, and the caller can check status later if needed:

```json
// Request
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "call": {
    "function": "reports.generate",
    "version": "1.0.0",
    "arguments": { "type": "annual" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:async",
      "options": { "preferred": true }
    }
  ]
}

// Response (immediate)
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:async",
      "data": {
        "operation_id": "op_xyz789",
        "status": "processing",
        "poll": {
          "function": "urn:cline:forrst:ext:async:fn:status",
          "version": "1.0.0",
          "arguments": { "operation_id": "op_xyz789" }
        },
        "retry_after": { "value": 5, "unit": "second" }
      }
    }
  ]
}
```

**For fast acknowledgment:**

Functions can validate, queue work, and return immediately:

```json
// Response in <5ms
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "result": {
    "queued": true,
    "job_id": "job_abc123"
  }
}
```

This provides the same speed as notifications, plus confirmation that the request was valid and accepted.

**For event emission:**

Use message queues directly. If you're emitting events without needing Forrst's request/response semantics, the queue transport is more appropriate than wrapping events in Forrst protocol overhead.

---

## How does Forrst handle long-running operations?

Forrst provides first-class support for asynchronous operations. See [Async](extensions/async.md) for full details.

### Quick Overview

**Request async processing:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "call": {
    "function": "exports.generate",
    "version": "1.0.0",
    "arguments": { "format": "csv" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:async",
      "options": { "preferred": true }
    }
  ]
}
```

**Server accepts and returns operation ID:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:async",
      "data": {
        "operation_id": "op_xyz789",
        "status": "processing",
        "poll": {
          "function": "urn:cline:forrst:ext:async:fn:status",
          "version": "1.0.0",
          "arguments": { "operation_id": "op_xyz789" }
        }
      }
    }
  ]
}
```

**Poll for status:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_poll",
  "call": {
    "function": "urn:cline:forrst:ext:async:fn:status",
    "version": "1.0.0",
    "arguments": {
      "operation_id": "op_xyz789"
    }
  }
}
```

**Get result when complete:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_poll",
  "result": {
    "operation_id": "op_xyz789",
    "status": "completed",
    "result": {
      "download_url": "https://..."
    }
  }
}
```

### System Functions for Operations

- `urn:cline:forrst:ext:async:fn:status` — Check operation status
- `urn:cline:forrst:ext:async:fn:cancel` — Cancel a pending operation
- `urn:cline:forrst:ext:async:fn:list` — List operations for the caller

See [System Functions](system-functions.md) for details.

---

## Why per-function versioning instead of API versioning?

Traditional REST APIs version the entire API: `/api/v1/`, `/api/v2/`. Forrst versions each function independently.

### The Problem with API Versioning

When you version the entire API, unrelated changes force version bumps everywhere:

```
/api/v1/orders    ← Unchanged, but stuck at "v1"
/api/v1/users     ← The actual breaking change
/api/v1/products  ← Unchanged, but stuck at "v1"
```

Consumers must upgrade everything when they only needed one change. Teams must coordinate releases across unrelated domains.

### Per-Function Versioning

```
orders.create@1.0.0   ← Untouched
users.get@2.0.0       ← Evolved independently
products.list@1.0.0   ← Untouched
```

Benefits:

- Teams evolve functions without coordinating releases
- Consumers upgrade function-by-function
- Deprecation is surgical: sunset `orders.create@1.0.0`, not "all of v1"
- No version sprawl where everything is "v7" but 90% unchanged

See [Versioning](versioning.md) for full details.

---

## Why is the `id` field a string, not a number?

JSON numbers have precision limits. JavaScript (and many JSON parsers) use IEEE 754 double-precision floats, which can only safely represent integers up to 2^53.

```javascript
// JavaScript precision loss
JSON.parse('{"id": 9007199254740993}')
// { id: 9007199254740992 } — Wrong!
```

String IDs avoid this entirely and support richer formats:

- UUID v4: `"550e8400-e29b-41d4-a716-446655440000"`
- Prefixed: `"req_abc123xyz"`
- ULID: `"01ARZ3NDEKTSV4RRFFQ69G5FAV"`

---

## How does Forrst use HTTP status codes?

Forrst uses a dual-channel approach for error reporting when using HTTP transport:

1. **HTTP status codes** — Semantic status codes (400, 404, 500, etc.) indicate the error category
2. **`errors` array** — Provides detailed, structured error information with specific error codes

### Why Both Channels?

**HTTP status codes enable infrastructure:**

- Load balancers can route based on error status
- HTTP middleware can retry on 5xx without parsing JSON
- Monitoring tools can track error rates by status code
- Caches can handle 404s without inspecting body

**The `errors` array provides precision:**

- Machine-readable error codes (`FUNCTION_NOT_FOUND` vs `NOT_FOUND`)
- Multiple validation errors in a single response
- Source pointers to specific invalid fields
- Structured error details for client handling

### Status Code Mapping

Common mappings:

- `200 OK` — Success (no errors)
- `400 Bad Request` — `PARSE_ERROR`, `INVALID_REQUEST`, `INVALID_ARGUMENTS`
- `404 Not Found` — `NOT_FOUND`, `FUNCTION_NOT_FOUND`
- `429 Too Many Requests` — `RATE_LIMITED`
- `500 Internal Server Error` — `INTERNAL_ERROR`
- `503 Service Unavailable` — `UNAVAILABLE`, `SERVER_MAINTENANCE`

See [Transport](transport.md) for complete mappings and [Errors](errors.md) for the full error specification.

### Transport-Agnostic Design

While HTTP uses status codes, other transports (message queues, Unix sockets) rely solely on the `errors` array. This dual-channel approach works across all transports while enabling HTTP-specific optimizations.
