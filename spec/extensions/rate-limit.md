---
title: Rate Limit
description: Request throttling and usage visibility
---

# Rate Limit

> Request throttling and usage visibility

**Extension URN:** `urn:forrst:ext:rate-limit`

---

## Overview

The rate limit extension provides standardized mechanisms for request throttling, following [draft-ietf-httpapi-ratelimit-headers](https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/) (RateLimit Header Fields for HTTP):

1. **Proactive visibility** — Clients see usage before hitting limits
2. **Clear errors** — Structured error when limits are exceeded
3. **Recovery guidance** — Explicit retry timing

This extension differs from [Quota](quota.md) which tracks resource consumption limits (API calls per month, storage used). Rate limiting focuses on request throughput protection.

---

## When to Use

Rate limiting SHOULD be used for:
- Public-facing APIs
- Services requiring overload protection
- Multi-tenant systems with fairness requirements
- Functions with expensive operations

Rate limiting MAY NOT be needed for:
- Internal service-to-service calls with trusted callers
- Queue-based async processing
- Services with other throttling mechanisms (transport layer, load balancer)

---

## Options (Request)

The rate limit extension typically requires no request options. Clients simply include the extension to receive rate limit metadata:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `scope` | string | No | Request specific scope info (`global`, `service`, `function`, `user`) |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `limit` | integer | Maximum requests allowed in the window |
| `used` | integer | Requests used in current window |
| `remaining` | integer | Requests remaining in current window |
| `window` | object | Time window duration (value/unit) |
| `resets_in` | object | Time until window resets (value/unit) |
| `scope` | string | Scope this limit applies to |
| `warning` | string | Optional warning when approaching limit |

---

## Behavior

### Standard Response

When the rate limit extension is included:

1. Server MUST include rate limit data in extension response
2. Server MUST accurately report `remaining` count
3. Server SHOULD include `warning` when approaching limit (e.g., < 10% remaining)

### Rate Limit Exceeded

When a client exceeds the rate limit:

1. Server MUST return `RATE_LIMITED` error
3. Server MUST include `retry_after` in error details
4. Server MUST still include extension data with current status

### Multiple Scopes

When multiple rate limit scopes apply, servers SHOULD return all applicable limits:

```json
{
  "urn": "urn:forrst:ext:rate-limit",
  "data": {
    "scopes": {
      "global": {
        "limit": 10000,
        "used": 4523,
        "remaining": 5477,
        "window": { "value": 1, "unit": "minute" },
        "resets_in": { "value": 32, "unit": "second" }
      },
      "service": {
        "limit": 1000,
        "used": 847,
        "remaining": 153,
        "window": { "value": 1, "unit": "minute" },
        "resets_in": { "value": 32, "unit": "second" }
      }
    }
  }
}
```

---

## Rate Limit Scopes

| Scope | Description |
|-------|-------------|
| `global` | Across all clients (system protection) |
| `service` | Per calling service (identified by `context.caller`) |
| `function` | Per function (different limits per operation) |
| `user` | Per authenticated user |

---

## Examples

### Request with Extension

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "call": {
    "function": "orders.create",
    "version": "1.0.0",
    "arguments": {
      "customer_id": 42,
      "items": [{ "sku": "WIDGET-01", "quantity": 1 }]
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:rate-limit",
      "options": {}
    }
  ]
}
```

### Normal Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": {
    "order_id": 456,
    "status": "created"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:rate-limit",
      "data": {
        "limit": 1000,
        "used": 42,
        "remaining": 958,
        "window": { "value": 1, "unit": "minute" },
        "resets_in": { "value": 47, "unit": "second" },
        "scope": "service"
      }
    }
  ]
}
```

### Approaching Limit

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_456",
  "result": { "success": true },
  "extensions": [
    {
      "urn": "urn:forrst:ext:rate-limit",
      "data": {
        "limit": 1000,
        "used": 985,
        "remaining": 15,
        "window": { "value": 1, "unit": "minute" },
        "resets_in": { "value": 12, "unit": "second" },
        "scope": "service",
        "warning": "Rate limit nearly exhausted"
      }
    }
  ]
}
```

### Rate Limited Error

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_789",
  "result": null,
  "errors": [{
    "code": "RATE_LIMITED",
    "message": "Rate limit exceeded for orders.create",
    "details": {
      "limit": 100,
      "used": 100,
      "window": { "value": 1, "unit": "minute" },
      "retry_after": { "value": 23, "unit": "second" },
      "scope": "function",
      "function": "orders.create"
    }
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:rate-limit",
      "data": {
        "limit": 100,
        "used": 100,
        "remaining": 0,
        "window": { "value": 1, "unit": "minute" },
        "resets_in": { "value": 23, "unit": "second" },
        "scope": "function"
      }
    }
  ]
}
```

### Multiple Scopes

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_multi",
  "result": { "success": true },
  "extensions": [
    {
      "urn": "urn:forrst:ext:rate-limit",
      "data": {
        "scopes": {
          "global": {
            "limit": 10000,
            "used": 4523,
            "remaining": 5477,
            "window": { "value": 1, "unit": "minute" },
            "resets_in": { "value": 32, "unit": "second" }
          },
          "service": {
            "limit": 1000,
            "used": 153,
            "remaining": 847,
            "window": { "value": 1, "unit": "minute" },
            "resets_in": { "value": 32, "unit": "second" }
          },
          "function": {
            "limit": 100,
            "used": 45,
            "remaining": 55,
            "window": { "value": 1, "unit": "minute" },
            "resets_in": { "value": 32, "unit": "second" }
          }
        }
      }
    }
  ]
}
```

---

## HTTP Mapping

Rate limit errors MUST map to HTTP 429 per [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110) Section 15.5.29:

| Error Code | HTTP Status | Headers |
|------------|-------------|---------|
| `RATE_LIMITED` | `429 Too Many Requests` | `Retry-After`, `RateLimit-*` |

### Retry-After Header

Servers MUST include the `Retry-After` HTTP header per [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110) Section 10.2.3:

```http
HTTP/1.1 429 Too Many Requests
Content-Type: application/json
Retry-After: 23
RateLimit-Limit: 100
RateLimit-Remaining: 0
RateLimit-Reset: 23

{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_789",
  "errors": [{ ... }]
}
```

The `Retry-After` value MUST be in seconds and MUST match the `retry_after.value` in error details (when unit is seconds).

---

## Client Behavior

### Proactive Throttling

Clients SHOULD monitor `remaining` and throttle requests before hitting limits:

```
if (remaining < threshold) {
    delay = calculate_backoff(remaining, resets_in)
    wait(delay)
}
```

### Handling RATE_LIMITED

When receiving `RATE_LIMITED` error:

1. Extract `retry_after` from error details
2. Wait the specified duration
3. Retry with exponential backoff if still limited
4. Set maximum retry attempts

Clients MUST NOT retry immediately without waiting.

### Backoff Strategy

Recommended exponential backoff:

```
wait_time = min(retry_after * (2 ^ attempt), max_wait)
```

Where:
- `retry_after` — From error details
- `attempt` — Retry attempt number (0, 1, 2, ...)
- `max_wait` — Maximum wait time (e.g., 5 minutes)

---

## Server Implementation

### Rate Limit Algorithms

Common algorithms:

| Algorithm | Description |
|-----------|-------------|
| Fixed Window | Reset counter at fixed intervals |
| Sliding Window | Rolling time window |
| Token Bucket | Tokens replenish over time |
| Leaky Bucket | Requests drain at constant rate |

### Requirements

Servers implementing rate limiting MUST:

1. Return `RATE_LIMITED` error when limit exceeded
2. Include `retry_after` in error details
4. Return extension data on every response (when extension requested)

Servers SHOULD:

1. Use consistent window boundaries across requests
2. Document rate limit policies
3. Include `warning` when approaching limit

---

## Discovery

Clients can discover rate limit policies via `urn:cline:forrst:fn:capabilities`:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_caps",
  "call": {
    "function": "urn:cline:forrst:fn:capabilities",
    "version": "1.0.0",
    "arguments": {}
  }
}
```

Response:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_caps",
  "result": {
    "service": "orders-api",
    "extensions": [
      {
        "urn": "urn:forrst:ext:rate-limit",
        "documentation": "https://docs.example.com/rate-limits"
      }
    ],
    "rate_limits": [
      {
        "scope": "service",
        "limit": 1000,
        "window": { "value": 1, "unit": "minute" }
      },
      {
        "scope": "function",
        "function": "orders.create",
        "limit": 100,
        "window": { "value": 1, "unit": "minute" }
      }
    ]
  }
}
```

---

## Rate Limit vs Quota

| Aspect | Rate Limit | Quota |
|--------|------------|-------|
| Purpose | Throughput protection | Resource consumption tracking |
| Time scale | Short (seconds/minutes) | Long (hours/days/months) |
| Reset | Automatic window reset | Manual or billing cycle |
| Example | 1000 req/minute | 10,000 API calls/month |
| Error code | `RATE_LIMITED` | `QUOTA_EXCEEDED` |

Use rate limiting for burst protection, quota for usage-based limits.
