---
title: Forrst Protocol Specification
description: An intra-service RPC protocol with per-function versioning
---

# Forrst Protocol Specification

> An intra-service RPC protocol with per-function versioning

**Protocol Version:** 0.1.0 (Draft)

---

## Overview

Forrst is a JSON-based RPC protocol designed for internal microservice communication. It prioritizes:

- **Per-function versioning** — Evolve functions independently, not monolithic API versions
- **Built-in observability** — Tracing context propagates through call chains
- **Explicit retry semantics** — Idempotency and deadlines are first-class
- **Extensibility** — Clean extension mechanism for optional features
- **Simplicity** — Easy to implement in any language

---

## Quick Start Guide

Get a Forrst endpoint running in 5 minutes:

### 1. Define Your Function

```json
{
  "function": "users.get",
  "version": "1.0.0",
  "description": "Retrieve a user by ID",
  "arguments": {
    "type": "object",
    "properties": {
      "id": { "type": "integer" }
    },
    "required": ["id"]
  }
}
```

### 2. Build a Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": { "id": 42 }
  }
}
```

### 3. Return a Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "result": {
    "id": 42,
    "name": "Jane Doe",
    "email": "jane@example.com"
  }
}
```

### 4. Handle Errors

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "result": null,
  "errors": [{
    "code": "NOT_FOUND",
    "message": "User not found",
  }]
}
```

### 5. Add Observability (Optional)

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": { "id": 42 }
  },
  "context": {
    "caller": "api-gateway"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "options": {
        "trace_id": "tr_abc123"
      }
    }
  ]
}
```

See [Protocol](protocol.md) for complete field definitions.

---

## Specification Documents

### Core Protocol

| Document | Description |
|----------|-------------|
| [Protocol](protocol.md) | Request and response structure, field definitions |
| [Document Structure](document-structure.md) | Top-level structure of requests and responses |
| [Errors](errors.md) | Error handling, error codes, multiple errors, source pointers |
| [Versioning](versioning.md) | Protocol versioning and per-function versioning |

### Data & Resources

| Document | Description |
|----------|-------------|
| [Resource Objects](resource-objects.md) | Recommended structure for domain entities (type/id/attributes) |

### Query Extension

These features are part of the [Query Extension](extensions/query.md) (`urn:forrst:ext:query`):

| Document | Description |
|----------|-------------|
| [Filtering](extensions/query.md#filtering) | Query filter syntax and operators |
| [Sorting](extensions/query.md#sorting) | Sort criteria for collection queries |
| [Pagination](extensions/query.md#pagination) | Standard patterns for paginated list operations |
| [Sparse Fieldsets](extensions/query.md#sparse-fieldsets) | Request only the fields you need |
| [Relationships](extensions/query.md#relationships) | Including and querying related resources |

### Request Features

| Document | Description |
|----------|-------------|
| [Context](context.md) | Context propagation for tracing and metadata |

### Extensions

| Document | Description |
|----------|-------------|
| [Extensions](extensions/index.md) | Extension mechanism for optional capabilities |
| [Query](extensions/query.md) | Filtering, sorting, pagination, relationships |
| [Rate Limit](extensions/rate-limit.md) | Request throttling and usage visibility |
| [Async](extensions/async.md) | Asynchronous operation patterns |
| [Deadline](extensions/deadline.md) | Request timeouts to prevent cascading failures |

### Transport

| Document | Description |
|----------|-------------|
| [Transport](transport.md) | HTTP and message queue bindings |

### System

| Document | Description |
|----------|-------------|
| [System Functions](system-functions.md) | Reserved namespaces and built-in functions |
| [Description](description.md) | Machine-readable API description (OpenRPC-style) |

---

## Terminology

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD", "SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL" in these documents are to be interpreted as described in [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## Standards References

This specification builds upon the following IETF and W3C standards:

### Normative References

| Standard | Title | Usage in Forrst |
|----------|-------|---------------|
| [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119) | Key words for use in RFCs | Requirement levels (MUST, SHOULD, MAY) |
| [RFC 3339](https://www.rfc-editor.org/rfc/rfc3339) | Date and Time on the Internet: Timestamps | All timestamp fields (ISO 8601 profile) |
| [RFC 6901](https://www.rfc-editor.org/rfc/rfc6901) | JavaScript Object Notation (JSON) Pointer | Error source pointers |
| [RFC 8141](https://www.rfc-editor.org/rfc/rfc8141) | Uniform Resource Names (URNs) | Extension identifiers (`urn:forrst:ext:*`) |
| [RFC 8259](https://www.rfc-editor.org/rfc/rfc8259) | The JavaScript Object Notation (JSON) Data Interchange Format | Document encoding |
| [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110) | HTTP Semantics | Status codes, headers, caching, authentication |

### Informative References

| Standard | Title | Usage in Forrst |
|----------|-------|---------------|
| [RFC 6750](https://www.rfc-editor.org/rfc/rfc6750) | OAuth 2.0 Bearer Token Usage | Bearer token authentication |
| [RFC 8446](https://www.rfc-editor.org/rfc/rfc8446) | The Transport Layer Security (TLS) Protocol Version 1.3 | mTLS authentication |
| [W3C Trace Context](https://www.w3.org/TR/trace-context/) | Distributed Tracing Context Propagation | Tracing extension interoperability |
| [draft-ietf-httpapi-ratelimit-headers](https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/) | RateLimit Header Fields for HTTP | Rate limit response headers |
| [draft-ietf-httpapi-idempotency-key-header](https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/) | The Idempotency-Key HTTP Header Field | Idempotency extension design |

---

## Complete Example

### Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_xyz789",
  "call": {
    "function": "orders.create",
    "version": "2.0.0",
    "arguments": {
      "customer_id": 42,
      "items": [
        { "sku": "WIDGET-01", "quantity": 2 }
      ]
    }
  },
  "context": {
    "caller": "checkout-service"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:deadline",
      "options": { "value": 5, "unit": "second" }
    },
    {
      "urn": "urn:forrst:ext:tracing",
      "options": {
        "trace_id": "tr_8f3a2b1c",
        "span_id": "sp_4d5e6f"
      }
    }
  ]
}
```

### Success Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_xyz789",
  "result": {
    "order_id": 12345,
    "status": "pending"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "tr_8f3a2b1c",
        "span_id": "span_server_001",
        "duration": { "value": 127, "unit": "millisecond" }
      }
    }
  ],
  "meta": {
    "node": "orders-api-2"
  }
}
```

### Error Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_xyz789",
  "result": null,
  "errors": [
    {
      "code": "INVALID_ARGUMENTS",
      "message": "Customer not found",
      "source": {
        "pointer": "/call/arguments/customer_id"
      }
    }
  ],
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "tr_8f3a2b1c",
        "span_id": "span_server_002",
        "duration": { "value": 23, "unit": "millisecond" }
      }
    }
  ],
  "meta": {
    "node": "orders-api-1"
  }
}
```

---

## Transport

Forrst is transport-agnostic. Common transports:

- **HTTP** — POST to service endpoint, JSON body
- **Message Queue** — Async processing via RabbitMQ, Redis, etc.
- **Unix Socket** — Local inter-process communication

See [Transport](transport.md) for detailed bindings.

---

## Implementation Checklist

Use this checklist when implementing a Forrst client or server:

### Server Implementation

- [ ] Parse and validate `protocol` field (reject unknown versions)
- [ ] Echo `id` field in all responses
- [ ] Route requests by `call.function` and `call.version`
- [ ] Validate `call.arguments` against function schema
- [ ] Return `errors` array for all error responses
- [ ] Include `code` and `message` in all errors
- [ ] Support `source.pointer` for validation errors (JSON Pointer format)
- [ ] Implement standard error codes (`NOT_FOUND`, `INVALID_ARGUMENTS`, etc.)
- [ ] Handle deadline extension — return `DEADLINE_EXCEEDED` if exceeded
- [ ] Propagate tracing extension (`trace_id`, `span_id`) to downstream calls
- [ ] Include duration via tracing extension in responses

### Client Implementation

- [ ] Generate unique `id` for each request
- [ ] Use deadline extension for all production calls
- [ ] Propagate trace context from incoming requests
- [ ] Handle `errors` array response format
- [ ] Respect `Retry-After` hints in rate limit errors

### Optional Features

- [ ] Extensions: Declare in `extensions` array with `urn` and `options` objects
- [ ] Async: Implement `urn:cline:forrst:ext:async:fn:status` polling
- [ ] Idempotency: Use idempotency extension for safe retries
- [ ] Discovery: Implement `forrst.functions` for introspection
- [ ] Health: Implement `urn:cline:forrst:fn:health` with component checks

---

## JSON-RPC Migration Guide

Migrating from JSON-RPC 2.0 to Forrst:

| JSON-RPC | Forrst | Notes |
|----------|------|-------|
| `jsonrpc: "2.0"` | `protocol: "forrst/0.1"` | Version identifier |
| `method` | `call.function` | Function name |
| — | `call.version` | **New:** Per-function versioning |
| `params` (array/object) | `call.arguments` | Always object |
| `result` | `result` | Same semantics |
| `error.code` (integer) | `errors[].code` (string) | String codes: `NOT_FOUND`, `INVALID_ARGUMENTS` |
| `error.message` | `errors[].message` | Same semantics |
| `error.data` | `errors[].details` | Renamed |
| — | `errors[].source` | **New:** JSON Pointer to error location |
| — | `context` | **New:** Trace propagation |
| — | `extensions` | **New:** Optional capabilities (deadline, tracing, etc.) |
| — | `meta` | **New:** Response metadata |

### Key Differences

1. **No notifications** — All requests require responses. Use async extension for fire-and-forget with tracking.

2. **No batch requests** — Make concurrent HTTP/2 requests instead. See [FAQ](faq.md) for rationale.

3. **Per-function versioning** — Version each function independently instead of the entire API.

4. **String error codes** — Use descriptive `SCREAMING_SNAKE_CASE` codes instead of integers.

5. **Built-in observability** — Trace context propagates automatically through `context` field.

### Migration Example

**JSON-RPC 2.0:**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "users.get",
  "params": { "id": 42 }
}
```

**Forrst:**
```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": { "id": 42 }
  }
}
```

---

## Pagination Decision Matrix

Choose the right pagination strategy for your use case:

| Strategy | Best For | Trade-offs |
|----------|----------|------------|
| **Offset** | Admin UIs, simple lists, "jump to page" | Inconsistent on writes, slow for large offsets |
| **Cursor** | Infinite scroll, real-time feeds | No random access, opaque tokens |
| **Keyset** | Large datasets, time-series | Requires stable sort column, no count |

### Decision Flowchart

```
Need "jump to page N"?
  └─ Yes → Offset pagination
  └─ No → Dataset > 10K rows?
            └─ Yes → Keyset pagination
            └─ No → Real-time feed?
                      └─ Yes → Cursor pagination
                      └─ No → Cursor or Offset (preference)
```

### Quick Examples

**Offset** — Page 3 of 10 items:
```json
{ "page": { "offset": 20, "limit": 10 } }
```

**Cursor** — After specific position:
```json
{ "page": { "after": "eyJpZCI6MTAwfQ==", "limit": 10 } }
```

**Keyset** — After specific value:
```json
{ "page": { "after": { "created_at": "2024-01-15T10:30:00Z", "id": 500 }, "limit": 10 } }
```

See [Pagination](extensions/query.md#pagination) for complete specification.

---

## Security Considerations

### Authentication

Forrst does not specify authentication — it is a transport concern. Common patterns:

- **HTTP:** Bearer tokens in `Authorization` header
- **Message Queues:** Credentials in connection/channel configuration
- **mTLS:** Certificate-based service identity

### Authorization

Use the `context` field to propagate identity for authorization decisions:

```json
{
  "context": {
    "caller": "checkout-service",
    "user_id": "usr_123",
    "roles": ["admin", "billing"]
  }
}
```

Servers SHOULD validate authorization before processing and return `FORBIDDEN` for unauthorized requests.

### Input Validation

- **Always validate** `call.arguments` against your schema
- **Use `source.pointer`** to indicate exact location of invalid input
- **Sanitize** all user input before use in queries or commands
- **Limit** request body size at transport layer (recommended: 1 MB)

### Rate Limiting

- Implement rate limits per caller/function
- Return `RATE_LIMITED` with `retry_after` in details
- Use `context.caller` to identify requesting service
- See [Rate Limit Extension](extensions/rate-limit.md) for response format

### Secrets

- **Never** include secrets in `call.arguments` for logging safety
- Use reference IDs instead of raw credentials
- Redact sensitive fields in error details

---

## Changelog

### 0.1.0 (Draft)

- Initial specification
- Core protocol, errors, versioning
- Document structure and resource objects (JSON:API-style)
- Filtering with operators (equals, in, between, like, etc.)
- Sorting and sparse fieldsets
- Relationship inclusion and nested queries
- Context propagation
- Rate limiting with proactive visibility (IETF draft headers)
- Pagination patterns (offset, cursor, keyset)
- Extension mechanism
- Async patterns
- Transport bindings (HTTP, message queues)
- System functions and reserved namespaces
- Health protocol (urn:cline:forrst:fn:health) with component-level checks
- Discovery specification (OpenRPC-style introspection)
