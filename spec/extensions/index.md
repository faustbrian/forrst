---
title: Extensions
description: Extension mechanism for optional capabilities
---

# Extensions

> Extension mechanism for optional capabilities

---

## Overview

Extensions add optional capabilities to Forrst without modifying the core protocol. They enable experimentation and domain-specific features while maintaining interoperability.

---

## Extension Principles

1. **Explicit** — Extensions MUST be declared in the request
2. **Contained** — Extension data MUST be within the extension object
3. **Additive** — Extensions MUST only add, not modify core behavior
4. **Negotiated** — Both client and server MUST support the extension

---

## Extension Structure

### Request Format

Extensions are declared in the `extensions` array as objects:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "call": {
    "function": "reports.generate",
    "version": "1.0.0",
    "arguments": { "type": "quarterly" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:async",
      "options": {
        "preferred": true,
        "callback_url": "https://my-service.example.com/webhooks"
      }
    }
  ]
}
```

### Extension Object (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `urn` | string | Yes | Extension identifier URN |
| `options` | object | No | Extension-specific request options |

### Response Format

Responses mirror the request structure:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": { "queued": true },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "tr_abc123",
        "span_id": "span_server_001",
        "duration": { "value": 45, "unit": "millisecond" }
      }
    },
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

### Extension Object (Response)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `urn` | string | Yes | Extension identifier URN (echoed from request) |
| `data` | object | No | Extension-specific response data |

---

## Extension URN

Each extension is identified by a URN per [RFC 8141](https://www.rfc-editor.org/rfc/rfc8141) (Uniform Resource Names):

```
urn:forrst:ext:async
urn:forrst:ext:example:audit
```

**Rules:**
- MUST be a valid URN per RFC 8141
- SHOULD resolve to documentation
- SHOULD use HTTPS

---

## Server Handling

### Supported Extensions

When a server supports all declared extensions:

1. Process extension-specific options
2. Return response with same extensions (by URN)
3. Include extension-specific response data

### Unsupported Extensions

When a server does not support a declared extension:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [{
    "code": "EXTENSION_NOT_SUPPORTED",
    "message": "Extension not supported: urn:forrst:ext:example:unknown",
    "details": {
      "unsupported": ["urn:forrst:ext:example:unknown"],
      "supported": ["urn:forrst:ext:async"]
    }
  }]
}
```

### Unknown Extensions

Servers MUST ignore extension objects with unrecognized URIs if the request does not require them.

### Per-Function Extension Support

While `urn:cline:forrst:fn:capabilities` returns server-wide extension support, individual functions MAY restrict which extensions they accept.

When a client requests an extension that a function doesn't support:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [{
    "code": "EXTENSION_NOT_APPLICABLE",
    "message": "Caching extension not applicable to write operations",
    "source": { "pointer": "/extensions/0" },
    "details": {
      "extension": "urn:forrst:ext:caching",
      "function": "orders.create"
    }
  }]
}
```

Use `urn:cline:forrst:fn:describe` to discover which extensions a specific function supports. See [System Functions](../system-functions.md#extension-support) for details.

---

## Creating Extensions

### Extension Specification

An extension specification MUST define:

1. **URN** — Unique identifier
2. **Options** — Request options the extension accepts
3. **Data** — Response data the extension returns
4. **Behavior** — Processing rules
5. **Examples** — Usage examples

### Example Extension Spec

```markdown
# Audit Extension

**URN:** urn:forrst:ext:audit

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `actor.user_id` | string | Yes | User performing the action |
| `actor.ip_address` | string | No | Client IP address |
| `actor.reason` | string | No | Reason for action |

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `log_id` | string | Audit log entry identifier |
| `logged_at` | string | ISO 8601 timestamp |

## Behavior

When the audit extension is included:
1. Server MUST log the action with actor details
2. Server MUST return `log_id` referencing the log entry
3. Log entries MUST be immutable once created
```

---

## Official Extensions

| Extension | URN | Description |
|-----------|-----|-------------|
| [Async](async.md) | `urn:forrst:ext:async` | Long-running operations with polling/callbacks |
| [Caching](caching.md) | `urn:forrst:ext:caching` | ETags, cache hints, conditional requests |
| [Cancellation](cancellation.md) | `urn:forrst:ext:cancellation` | Request cancellation for synchronous operations |
| [Deadline](deadline.md) | `urn:forrst:ext:deadline` | Request timeouts to prevent cascading failures |
| [Deprecation](deprecation.md) | `urn:forrst:ext:deprecation` | Warnings for deprecated functions/versions |
| [Dry Run](dry-run.md) | `urn:forrst:ext:dry-run` | Validate operations without executing |
| [Idempotency](idempotency.md) | `urn:forrst:ext:idempotency` | Request deduplication for safe retries |
| [Locale](locale.md) | `urn:forrst:ext:locale` | Internationalization and localization preferences |
| [Maintenance](maintenance.md) | `urn:forrst:ext:maintenance` | Scheduled downtime and graceful degradation |
| [Priority](priority.md) | `urn:forrst:ext:priority` | Request priority hints for queue management |
| [Query](query.md) | `urn:forrst:ext:query` | Filtering, sorting, pagination, relationships |
| [Quota](quota.md) | `urn:forrst:ext:quota` | Usage quotas and limits information |
| [Rate Limit](rate-limit.md) | `urn:forrst:ext:rate-limit` | Request throttling and usage visibility |
| [Redact](redact.md) | `urn:forrst:ext:redact` | Sensitive field masking and data protection |
| [Replay](replay.md) | `urn:forrst:ext:replay` | Request journaling for deferred execution |
| [Retry](retry.md) | `urn:forrst:ext:retry` | Structured retry guidance for failed requests |
| [Simulation](simulation.md) | `urn:forrst:ext:simulation` | Sandbox mode with predefined scenarios |
| [Stream](stream.md) | `urn:forrst:ext:stream` | Server-Sent Events streaming for real-time responses |
| [Tracing](tracing.md) | `urn:forrst:ext:tracing` | Distributed tracing for observability |

---

## Extension Discovery

Clients MAY discover supported extensions:

```json
// Request
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_discover",
  "call": {
    "function": "urn:cline:forrst:fn:capabilities",
    "version": "1.0.0",
    "arguments": {}
  }
}

// Response
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_discover",
  "result": {
    "protocol_versions": ["0.1.0"],
    "extensions": [
      {
        "urn": "urn:forrst:ext:async",
        "documentation": "urn:forrst:ext:async"
      }
    ]
  }
}
```

See [System Functions](../system-functions.md) for details.

---

## Examples

### Request with Extension

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_ext",
  "call": {
    "function": "users.delete",
    "version": "1.0.0",
    "arguments": { "user_id": 42 }
  },
  "context": {
    "trace_id": "tr_abc",
    "span_id": "sp_123"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:audit",
      "options": {
        "actor": {
          "user_id": "admin_1",
          "ip_address": "192.168.1.1",
          "reason": "GDPR deletion request"
        }
      }
    }
  ]
}
```

### Response with Extension

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_ext",
  "result": {
    "deleted": true
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "tr_abc123",
        "span_id": "span_server_001",
        "duration": { "value": 45, "unit": "millisecond" }
      }
    },
    {
      "urn": "urn:forrst:ext:audit",
      "data": {
        "log_id": "audit_log_789",
        "logged_at": "2024-03-15T14:30:00Z"
      }
    }
  ]
}
```

### Multiple Extensions

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_multi",
  "call": {
    "function": "reports.generate",
    "version": "1.0.0",
    "arguments": { "type": "quarterly" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:audit",
      "options": {
        "actor": { "user_id": "admin_1" }
      }
    },
    {
      "urn": "urn:forrst:ext:async",
      "options": {
        "preferred": true
      }
    }
  ]
}
```

### Multiple Extensions Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_multi",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "tr_abc123",
        "span_id": "span_server_001",
        "duration": { "value": 12, "unit": "millisecond" }
      }
    },
    {
      "urn": "urn:forrst:ext:audit",
      "data": {
        "log_id": "audit_log_790"
      }
    },
    {
      "urn": "urn:forrst:ext:async",
      "data": {
        "operation_id": "op_abc123",
        "status": "processing",
        "poll": {
          "function": "urn:cline:forrst:ext:async:fn:status",
          "version": "1.0.0",
          "arguments": { "operation_id": "op_abc123" }
        },
        "retry_after": { "value": 5, "unit": "second" }
      }
    }
  ]
}
```
