---
title: Maintenance
description: Scheduled downtime and graceful service degradation
---

# Maintenance

> Scheduled downtime and graceful service degradation

**Extension URN:** `urn:forrst:ext:maintenance`

---

## Overview

The maintenance extension enables servers to signal scheduled unavailability at the server or function level. Unlike `FUNCTION_DISABLED` (which indicates a function is turned off for various reasons), maintenance errors specifically indicate temporary unavailability with a defined recovery window.

Key differences:

| Error | Meaning | HTTP | Recovery |
|-------|---------|------|----------|
| `FUNCTION_DISABLED` | Turned off (feature flag, deprecated, etc.) | 400/403 | Unknown |
| `FUNCTION_MAINTENANCE` | Function under maintenance | 503 | Defined window |
| `SERVER_MAINTENANCE` | Entire server under maintenance | 503 | Defined window |

---

## When to Use

Maintenance mode SHOULD be used for:
- Scheduled deployments
- Database migrations
- Infrastructure upgrades
- Planned downtime windows
- Function-specific maintenance (e.g., reindexing search)

Maintenance mode SHOULD NOT be used for:
- Unexpected outages (use `UNAVAILABLE` or `INTERNAL_ERROR`)
- Feature flags (use `FUNCTION_DISABLED`)
- Rate limiting (use `RATE_LIMITED`)
- Permanent removal (use `FUNCTION_NOT_FOUND` or deprecation)

---

## Options (Request)

The maintenance extension requires no request options. It is server-initiated only.

---

## Data (Response)

When the server is in maintenance mode, responses include:

| Field | Type | Description |
|-------|------|-------------|
| `scope` | string | `server` or `function` |
| `function` | string | Affected function (if scope is `function`) |
| `reason` | string | Human-readable explanation |
| `started_at` | string | ISO 8601 timestamp when maintenance began |
| `until` | string | ISO 8601 timestamp when maintenance ends (if known) |
| `retry_after` | object | Duration before retry (value/unit) |

---

## Error Codes

### SERVER_MAINTENANCE

The entire Forrst server is under maintenance.

| Field | Value |
|-------|-------|
| `code` | `SERVER_MAINTENANCE` |
| HTTP Status | `503 Service Unavailable` |

**Example:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [{
    "code": "SERVER_MAINTENANCE",
    "message": "Service under scheduled maintenance",
    "details": {
      "reason": "Database migration in progress",
      "started_at": "2024-01-15T10:00:00Z",
      "until": "2024-01-15T12:00:00Z",
      "retry_after": { "value": 30, "unit": "minute" }
    }
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:maintenance",
      "data": {
        "scope": "server",
        "reason": "Database migration in progress",
        "started_at": "2024-01-15T10:00:00Z",
        "until": "2024-01-15T12:00:00Z",
        "retry_after": { "value": 30, "unit": "minute" }
      }
    }
  ]
}
```

### FUNCTION_MAINTENANCE

A specific function is under maintenance while other functions remain available.

| Field | Value |
|-------|-------|
| `code` | `FUNCTION_MAINTENANCE` |
| HTTP Status | `503 Service Unavailable` |

**Example:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_456",
  "result": null,
  "errors": [{
    "code": "FUNCTION_MAINTENANCE",
    "message": "Function reports.generate under maintenance",
    "details": {
      "function": "reports.generate",
      "reason": "Report engine upgrade",
      "started_at": "2024-01-15T10:00:00Z",
      "until": "2024-01-15T11:00:00Z",
      "retry_after": { "value": 15, "unit": "minute" }
    }
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:maintenance",
      "data": {
        "scope": "function",
        "function": "reports.generate",
        "reason": "Report engine upgrade",
        "started_at": "2024-01-15T10:00:00Z",
        "until": "2024-01-15T11:00:00Z",
        "retry_after": { "value": 15, "unit": "minute" }
      }
    }
  ]
}
```

---

## HTTP Mapping

Maintenance errors MUST map to HTTP 503:

| Error Code | HTTP Status | Headers |
|------------|-------------|---------|
| `SERVER_MAINTENANCE` | `503 Service Unavailable` | `Retry-After` |
| `FUNCTION_MAINTENANCE` | `503 Service Unavailable` | `Retry-After` |

### Retry-After Header

Servers MUST include the `Retry-After` HTTP header per [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110) Section 10.2.3:

```http
HTTP/1.1 503 Service Unavailable
Content-Type: application/json
Retry-After: 1800

{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "errors": [{ ... }]
}
```

The `Retry-After` value SHOULD be in seconds and MUST match the `retry_after` duration in the error details.

---

## Behavior

### Server Maintenance

When server maintenance is active:

1. Server MUST reject ALL function calls with `SERVER_MAINTENANCE`
2. Server MUST return HTTP 503
3. Server MUST include `Retry-After` header
4. Server SHOULD still respond to `urn:cline:forrst:fn:ping` and `urn:cline:forrst:fn:health`
5. `urn:cline:forrst:fn:health` MUST return `status: "unhealthy"` with maintenance details

### Function Maintenance

When function maintenance is active:

1. Server MUST reject calls to affected function with `FUNCTION_MAINTENANCE`
2. Server MUST return HTTP 503 for affected function
3. Server MUST allow calls to other functions
4. `urn:cline:forrst:fn:health` SHOULD report function status in `functions` object

### Health Check Integration

The maintenance extension integrates with `urn:cline:forrst:fn:health`:

```json
{
  "result": {
    "status": "degraded",
    "components": {
      "database": { "status": "healthy" },
      "cache": { "status": "healthy" }
    },
    "functions": {
      "reports.generate": {
        "status": "maintenance",
        "message": "Report engine upgrade",
        "until": "2024-01-15T11:00:00Z",
        "retry_after": { "value": 15, "unit": "minute" }
      },
      "orders.create": {
        "status": "healthy"
      }
    },
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

Note: Function status `maintenance` is distinct from `disabled`:

| Status | Meaning | Error Code |
|--------|---------|------------|
| `disabled` | Turned off (various reasons) | `FUNCTION_DISABLED` |
| `maintenance` | Scheduled downtime | `FUNCTION_MAINTENANCE` |

---

## Discovery

Clients can discover maintenance windows via `urn:cline:forrst:fn:capabilities`:

```json
{
  "result": {
    "service": "orders-api",
    "extensions": [
      {
        "urn": "urn:forrst:ext:maintenance",
        "documentation": "https://docs.example.com/maintenance"
      }
    ],
    "maintenance": {
      "status_url": "https://status.example.com",
      "schedule_url": "https://status.example.com/schedule"
    }
  }
}
```

---

## Client Behavior

### Handling Maintenance Errors

When receiving maintenance errors:

1. Extract `retry_after` from error details
2. Extract `until` if available for user display
3. Queue or defer the request (see [Replay](replay.md) extension)
4. Wait the specified duration before retry
5. Implement exponential backoff if still in maintenance

### User Communication

Clients SHOULD:
- Display the `reason` to users
- Show estimated recovery time from `until`
- Provide option to queue request for later (if replay is supported)

### Proactive Checks

Clients MAY call `urn:cline:forrst:fn:health` to check maintenance status before sending requests:

```json
{
  "call": {
    "function": "urn:cline:forrst:fn:health",
    "version": "1.0.0",
    "arguments": {
      "component": "self"
    }
  }
}
```

---

## Server Implementation

### Enabling Maintenance Mode

Servers SHOULD provide mechanisms to enable maintenance:

1. **Graceful activation** — Complete in-flight requests before entering maintenance
2. **Immediate activation** — Reject new requests immediately
3. **Scheduled activation** — Automatic activation at specified time

### Configuration

Recommended configuration options:

| Option | Description |
|--------|-------------|
| `enabled` | Boolean to enable/disable maintenance |
| `scope` | `server` or list of functions |
| `reason` | Human-readable message |
| `until` | Scheduled end time |
| `allow_health_checks` | Whether to allow `urn:cline:forrst:fn:ping`/`urn:cline:forrst:fn:health` |

### Graceful Drain

Before entering maintenance, servers SHOULD:

1. Start rejecting new requests
2. Wait for in-flight requests to complete (with timeout)
3. Enter full maintenance mode

---

## Examples

### Server Entering Maintenance

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_789",
  "result": null,
  "errors": [{
    "code": "SERVER_MAINTENANCE",
    "message": "Scheduled maintenance window",
    "details": {
      "reason": "Infrastructure upgrade",
      "started_at": "2024-01-15T02:00:00Z",
      "until": "2024-01-15T04:00:00Z",
      "retry_after": { "value": 2, "unit": "hour" }
    }
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:maintenance",
      "data": {
        "scope": "server",
        "reason": "Infrastructure upgrade",
        "started_at": "2024-01-15T02:00:00Z",
        "until": "2024-01-15T04:00:00Z",
        "retry_after": { "value": 2, "unit": "hour" }
      }
    }
  ]
}
```

### Unknown End Time

When maintenance duration is unknown:

```json
{
  "errors": [{
    "code": "FUNCTION_MAINTENANCE",
    "message": "Search index rebuild in progress",
    "details": {
      "function": "search.query",
      "reason": "Search index rebuild in progress",
      "started_at": "2024-01-15T10:00:00Z",
      "retry_after": { "value": 5, "unit": "minute" }
    }
  }]
}
```

Note: `until` is omitted when end time is unknown. Clients should use `retry_after` for polling.

### Health Check During Maintenance

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "health_001",
  "result": {
    "status": "unhealthy",
    "components": {
      "self": { "status": "healthy" },
      "database": { "status": "healthy" }
    },
    "maintenance": {
      "active": true,
      "reason": "Database migration",
      "until": "2024-01-15T12:00:00Z"
    },
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

---

## Error Codes Summary

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `SERVER_MAINTENANCE` | Yes | 503 | Entire server under scheduled maintenance |
| `FUNCTION_MAINTENANCE` | Yes | 503 | Specific function under scheduled maintenance |

---

## Related Extensions

- [Replay](replay.md) — Queue requests during maintenance for later processing
- [Rate Limit](rate-limit.md) — Throttling (different from maintenance)
- [Deadline](deadline.md) — Request timeout handling
