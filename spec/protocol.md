---
title: Protocol
description: Core request and response structure
---

# Protocol

> Core request and response structure

---

## Request Format

A Forrst request is a JSON object with the following structure:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_abc123",
  "call": {
    "function": "service.action",
    "version": "1.0.0",
    "arguments": { }
  },
  "context": { },
  "extensions": [ ]
}
```

### Top-Level Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `protocol` | string | Yes | Protocol identifier and version. MUST use format: `forrst/<major>.<minor>` |
| `id` | string | Yes | Unique request identifier. MUST correlate request to response. |
| `call` | object | Yes | The function invocation details |
| `context` | object | No | Propagated metadata. See [Context](context.md). |
| `extensions` | array | No | Extension objects with `urn` and `options`. See [Extensions](extensions/index.md). |

### Call Object

The `call` object specifies what function to invoke:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `function` | string | Yes | Function identifier MUST use `<service>.<action>` format |
| `version` | string | No | Function version. Defaults to latest. See [Versioning](versioning.md). |
| `arguments` | object | No | Function arguments. Defaults to empty object `{}` if omitted. |

### ID Field

The `id` field uniquely identifies a request attempt.

**Type:** String only. Implementations SHOULD use one of:
- UUID v4: `"550e8400-e29b-41d4-a716-446655440000"`
- Prefixed random: `"req_abc123xyz"`
- ULID: `"01ARZ3NDEKTSV4RRFFQ69G5FAV"`

The `id` field MUST NOT be:
- Null
- Empty string
- Numeric (to avoid JSON precision issues)
- An object or array

### Function Naming

Function names MUST use dot notation: `<service>.<action>`

Examples:
- `orders.create`
- `users.get`
- `inventory.reserve`

Function names beginning with `forrst.` are reserved. See [System Functions](system-functions.md).

Function names are case-sensitive. Implementations SHOULD use lowercase with underscores for multi-word actions: `orders.get_by_customer`.

---

## Response Format

A Forrst response is a JSON object with the following structure:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_abc123",
  "result": { },
  "meta": { }
}
```

### Top-Level Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `protocol` | string | Yes | Protocol identifier and version |
| `id` | string | Yes | Echoed from request. MUST correlate response to request. |
| `result` | any | Conditional | Function return value. MUST be present on success. |
| `errors` | array | Conditional | Array of error objects. Present when request fails. |
| `meta` | object | No | Response metadata |
| `async` | object | No | Async operation status. See [Async](extensions/async.md). |

### Result Field

On success, `result` contains the function's return value. The type depends on the function:
- Object: `{ "order_id": 123 }`
- Array: `[{ "id": 1 }, { "id": 2 }]`
- Scalar: `42`, `"ok"`, `true`
- Null: When function returns nothing

On error, `result` MUST be `null`.

### Error Fields

A response MUST contain exactly one of:
- `result` with value (success)
- `errors` with array of error objects (failure)

On error, `result` MUST be `null`. The `errors` array MUST contain at least one error.

See [Errors](errors.md) for error object structure.

### Meta Object

OPTIONAL metadata about the response:

| Field | Type | Description |
|-------|------|-------------|
| `node` | string | Identifier of the server node that handled the request |

> **Note:** Server-side processing duration is provided via the [Tracing Extension](extensions/tracing.md). Rate limiting information is provided via the [Rate Limit Extension](extensions/rate-limit.md).

Implementations MAY add additional fields to `meta`.

---

## Parse Errors

When a request cannot be parsed as valid JSON:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": null,
  "result": null,
  "errors": [
    {
      "code": "PARSE_ERROR",
      "message": "Invalid JSON: unexpected end of input",
      "source": {
        "position": 52
      }
    }
  ]
}
```

The `id` field MUST be `null` when the request's `id` could not be determined.

The `source.position` field SHOULD contain the byte offset where parsing failed, if available.

---

## Transport Conventions

### HTTP

When using HTTP transport:

| Aspect | Convention |
|--------|------------|
| Method | MUST use `POST` |
| Content-Type | MUST be `application/json` |
| Endpoint | Service-defined (e.g., `/forrst`, `/rpc`) |
| HTTP Status | MUST use semantic status codes reflecting error type |

When using HTTP transport, status codes MUST reflect the error type. Success responses return `200 OK`; error responses return the appropriate HTTP status code matching the Forrst error code.

See [Transport](transport.md) for complete HTTP status code mappings.

### Message Queues

When using message queue transport:

| Aspect | Convention |
|--------|------------|
| Message format | MUST be JSON-encoded Forrst request |
| Correlation | MUST use message correlation ID matching Forrst `id` |
| Reply queue | SHOULD specify in message properties |

---

## Examples

### Minimal Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "call": {
    "function": "health.check",
    "version": "1.0.0"
  }
}
```

### Minimal Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "result": { "status": "healthy" }
}
```

### Full Request

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
        "span_id": "sp_4d5e6f",
        "parent_span_id": "sp_1a2b3c"
      }
    }
  ]
}
```

### Full Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_xyz789",
  "result": {
    "order_id": 12345,
    "status": "pending",
    "created_at": "2024-01-15T10:30:00Z"
  },
  "meta": {
    "node": "orders-api-2"
  }
}
```
