---
title: Errors
description: Error handling, error codes, multiple errors, and source pointers
---

# Errors

> Error handling, error codes, multiple errors, and source pointers

---

## Error Object

An error object describes a single error:

```json
{
  "code": "INVALID_ARGUMENTS",
  "message": "Customer ID must be a positive integer",
  "source": {
    "pointer": "/call/arguments/customer_id"
  },
  "details": {
    "expected": "positive integer",
    "received": -1
  }
}
```

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `code` | string | Yes | Machine-readable error code. MUST be SCREAMING_SNAKE_CASE. |
| `message` | string | Yes | Human-readable error description. |
| `source` | object | No | Location of the error cause. |
| `details` | object | No | Additional structured error context. |

---

## Error Responses

Responses always use the `errors` field (plural array):

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [
    {
      "code": "INVALID_ARGUMENTS",
      "message": "Email is required",
      "source": { "pointer": "/call/arguments/email" }
    }
  ]
}
```

### Rules

- The `errors` array MUST contain at least one error
- Even single errors use the `errors` array format

### HTTP Status Codes

When using HTTP transport, the HTTP status code reflects the error type:

- **Single error**: Uses the error's specific HTTP status code (e.g., 404 for `NOT_FOUND`, 401 for `UNAUTHORIZED`)
- **Multiple errors**: Always returns `400 Bad Request` since HTTP can only express one status code and multiple errors may have different status codes

See [Transport](transport.md) for complete HTTP status code mappings.

---

## Source Object

The `source` object pinpoints where the error originated using JSON Pointer (RFC 6901):

### Pointer Field

Points to the request field that caused the error:

```json
{
  "source": {
    "pointer": "/call/arguments/customer_id"
  }
}
```

Common pointer patterns:

| Pointer | Description |
|---------|-------------|
| `/call/arguments/field` | Top-level argument |
| `/call/arguments/items/0/sku` | Array element field |
| `/call/arguments/address/city` | Nested object field |
| `/extensions/0/options/value` | Extension option field |
| `/context/tenant_id` | Context field |

### Position Field

For parse errors, indicates byte offset:

```json
{
  "source": {
    "position": 142
  }
}
```

### Rules

- `pointer` MUST use JSON Pointer syntax (RFC 6901)
- `pointer` MUST be relative to the request root
- `position` MUST be a zero-indexed byte offset
- A source object MUST contain `pointer` OR `position`, not both

---

## Standard Error Codes

### Protocol Errors

Errors in the Forrst envelope or protocol handling:

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `PARSE_ERROR` | No | 400 | Request is not valid JSON |
| `INVALID_REQUEST` | No | 400 | Request structure violates protocol |
| `INVALID_PROTOCOL_VERSION` | No | 400 | Unsupported protocol version |

### Function Errors

Errors in function resolution:

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `FUNCTION_NOT_FOUND` | No | 404 | Unknown function name |
| `VERSION_NOT_FOUND` | No | 404 | Unknown version for the function |
| `FUNCTION_DISABLED` | Yes | 503 | Function temporarily disabled |
| `INVALID_ARGUMENTS` | No | 400 | Arguments failed validation |
| `SCHEMA_VALIDATION_FAILED` | No | 422 | Arguments failed schema validation |
| `EXTENSION_NOT_SUPPORTED` | No | 400 | Requested extension not supported by server |
| `EXTENSION_NOT_APPLICABLE` | No | 400 | Requested extension not applicable to this function |

### Authentication/Authorization Errors

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `UNAUTHORIZED` | No | 401 | Authentication required or failed |
| `FORBIDDEN` | No | 403 | Authenticated but not permitted |

### Resource Errors

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `NOT_FOUND` | No | 404 | Requested resource does not exist |
| `CONFLICT` | No | 409 | Operation conflicts with current state |
| `GONE` | No | 410 | Resource existed but was deleted |

### Operational Errors

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `DEADLINE_EXCEEDED` | Yes | 408 | Request exceeded deadline |
| `RATE_LIMITED` | Yes | 429 | Too many requests |
| `INTERNAL_ERROR` | Yes | 500 | Unexpected server error |
| `UNAVAILABLE` | Yes | 503 | Service temporarily unavailable |
| `DEPENDENCY_ERROR` | Yes | 502 | Downstream service failed |

### Idempotency Errors

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `IDEMPOTENCY_CONFLICT` | No | 409 | Idempotency key reused with different arguments |
| `IDEMPOTENCY_PROCESSING` | Yes | 409 | Previous request with same key still processing |

### Async Errors

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `ASYNC_OPERATION_NOT_FOUND` | No | 404 | Unknown operation ID |
| `ASYNC_OPERATION_FAILED` | No | 500 | Async operation failed permanently |
| `ASYNC_CANNOT_CANCEL` | No | 400 | Operation cannot be cancelled (completed or not cancellable) |

### Batch Errors

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `BATCH_FAILED` | No | 400 | Atomic batch failed (one or more operations failed) |
| `BATCH_TOO_LARGE` | No | 400 | Too many operations or payload too large |
| `BATCH_TIMEOUT` | Yes | 504 | Batch execution exceeded timeout |

### Maintenance Errors

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `SERVER_MAINTENANCE` | Yes | 503 | Entire server under scheduled maintenance |
| `FUNCTION_MAINTENANCE` | Yes | 503 | Specific function under scheduled maintenance |

### Replay Errors

| Code | Retryable | HTTP | Description |
|------|-----------|------|-------------|
| `REPLAY_NOT_FOUND` | No | 404 | Unknown replay ID |
| `REPLAY_EXPIRED` | No | 410 | Request TTL exceeded |
| `REPLAY_ALREADY_COMPLETE` | No | 409 | Request already replayed |
| `REPLAY_CANCELLED` | No | 410 | Request was cancelled |

---

## Custom Error Codes

Applications MAY define additional error codes.

Custom codes SHOULD:
- Use SCREAMING_SNAKE_CASE
- Be prefixed with application/domain identifier
- Be documented for API consumers

Example:
```json
{
  "code": "ORDERS_INVENTORY_INSUFFICIENT",
  "message": "Not enough inventory for SKU WIDGET-01",
  "details": {
    "sku": "WIDGET-01",
    "requested": 10,
    "available": 3
  }
}
```

---

## Error Details

The `details` object provides structured context beyond the message:

### Validation Details

```json
{
  "code": "INVALID_ARGUMENTS",
  "message": "Validation failed",
  "source": { "pointer": "/call/arguments/email" },
  "details": {
    "constraint": "email_format",
    "value": "not-an-email"
  }
}
```

### Rate Limit Details

```json
{
  "code": "RATE_LIMITED",
  "message": "Too many requests",
  "details": {
    "limit": 100,
    "window": { "value": 1, "unit": "minute" },
    "retry_after": { "value": 5, "unit": "second" }
  }
}
```

### Dependency Details

```json
{
  "code": "DEPENDENCY_ERROR",
  "message": "Payment service unavailable",
  "details": {
    "dependency": "payments-api",
    "dependency_error": "connection timeout"
  }
}
```

---

## Examples

### Single Validation Error

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [
    {
      "code": "INVALID_ARGUMENTS",
      "message": "Customer ID is required",
      "source": {
        "pointer": "/call/arguments/customer_id"
      }
    }
  ]
}
```

### Multiple Validation Errors

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_456",
  "result": null,
  "errors": [
    {
      "code": "INVALID_ARGUMENTS",
      "message": "Email format is invalid",
      "source": { "pointer": "/call/arguments/email" },
      "details": { "constraint": "email_format" }
    },
    {
      "code": "INVALID_ARGUMENTS",
      "message": "Quantity must be at least 1",
      "source": { "pointer": "/call/arguments/items/0/quantity" },
      "details": { "constraint": "min", "min": 1, "actual": 0 }
    },
    {
      "code": "INVALID_ARGUMENTS",
      "message": "Unknown SKU",
      "source": { "pointer": "/call/arguments/items/1/sku" },
      "details": { "sku": "UNKNOWN-123" }
    }
  ]
}
```

### Parse Error

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": null,
  "result": null,
  "errors": [
    {
      "code": "PARSE_ERROR",
      "message": "Invalid JSON: unexpected token at position 89",
      "source": {
        "position": 89
      }
    }
  ]
}
```

### Rate Limit Error

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_789",
  "result": null,
  "errors": [
    {
      "code": "RATE_LIMITED",
      "message": "Rate limit exceeded",
      "details": {
        "limit": 1000,
        "window": { "value": 1, "unit": "hour" },
        "retry_after": { "value": 2, "unit": "minute" }
      }
    }
  ]
}
```
