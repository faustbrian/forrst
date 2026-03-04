---
title: Batch
description: Multiple operations in a single request
---

# Batch

> Multiple operations in a single request

**Extension URN:** `urn:forrst:ext:batch`

---

## Overview

The batch extension enables multiple operations in a single request. Reduces round trips and enables atomic transactions across operations.

---

## When to Use

Batch operations SHOULD be used for:
- Bulk create/update/delete operations
- Operations requiring atomic consistency
- Reducing network round trips
- Related operations that should succeed or fail together

Batch operations SHOULD NOT be used for:
- Unrelated operations
- Operations with complex interdependencies
- Very large batches (consider chunking into multiple requests)

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `mode` | string | Yes | `atomic` or `independent` |
| `operations` | array | Yes | List of operations to execute |
| `stop_on_error` | boolean | No | Stop processing on first error (independent mode only) |

### Operation Object

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | Unique identifier within batch |
| `function` | string | Yes | Function to call |
| `version` | string | Yes | Function version |
| `arguments` | object | Yes | Function arguments |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `mode` | string | Execution mode used |
| `results` | array | Results for each operation |
| `summary` | object | Aggregate statistics |

### Result Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Operation ID from request |
| `status` | integer | HTTP status code (200, 400, 429, etc.) |
| `result` | any | Operation result (if status 2xx) |
| `errors` | array | Error objects (if status 4xx/5xx) |

Status codes follow standard HTTP semantics. Use `0` for skipped operations (e.g., after `stop_on_error` triggers).

### Summary Object

| Field | Type | Description |
|-------|------|-------------|
| `total` | number | Total operations |
| `succeeded` | number | Successful operations |
| `failed` | number | Failed operations |
| `skipped` | number | Skipped operations |

---

## Behavior

### Atomic Mode

When `mode: "atomic"`:

1. Server MUST execute all operations in a transaction
2. If ANY operation fails, ALL operations MUST be rolled back
3. Server MUST return either all successes or all failures
4. Operations MAY be executed in any order

### Independent Mode

When `mode: "independent"`:

1. Server executes operations independently
2. Failed operations do NOT affect others
3. If `stop_on_error: true`, remaining operations are skipped after first error
4. Server SHOULD execute operations in order

---

## Examples

### Atomic Batch Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_batch",
  "call": {
    "function": "forrst.batch",
    "version": "1.0.0",
    "arguments": {}
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:batch",
      "options": {
        "mode": "atomic",
        "operations": [
          {
            "id": "op1",
            "function": "accounts.debit",
            "version": "1.0.0",
            "arguments": { "account_id": "A", "amount": 100 }
          },
          {
            "id": "op2",
            "function": "accounts.credit",
            "version": "1.0.0",
            "arguments": { "account_id": "B", "amount": 100 }
          }
        ]
      }
    }
  ]
}
```

### Atomic Success Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_batch",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:batch",
      "data": {
        "mode": "atomic",
        "results": [
          {
            "id": "op1",
            "status": 200,
            "result": { "new_balance": 400 }
          },
          {
            "id": "op2",
            "status": 200,
            "result": { "new_balance": 600 }
          }
        ],
        "summary": {
          "total": 2,
          "succeeded": 2,
          "failed": 0,
          "skipped": 0
        }
      }
    }
  ]
}
```

### Atomic Failure Response

When one operation fails, all are rolled back:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_batch",
  "result": null,
  "errors": [{
    "code": "BATCH_FAILED",
    "message": "Atomic batch failed: insufficient funds",
    "retryable": false
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:batch",
      "data": {
        "mode": "atomic",
        "results": [
          {
            "id": "op1",
            "status": 400,
            "errors": [{
              "code": "INSUFFICIENT_FUNDS",
              "message": "Account A has insufficient funds"
            }]
          },
          {
            "id": "op2",
            "status": 0
          }
        ],
        "summary": {
          "total": 2,
          "succeeded": 0,
          "failed": 1,
          "skipped": 1
        }
      }
    }
  ]
}
```

### Independent Batch Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_batch_ind",
  "call": {
    "function": "forrst.batch",
    "version": "1.0.0",
    "arguments": {}
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:batch",
      "options": {
        "mode": "independent",
        "stop_on_error": false,
        "operations": [
          {
            "id": "create1",
            "function": "users.create",
            "version": "1.0.0",
            "arguments": { "email": "alice@example.com", "name": "Alice" }
          },
          {
            "id": "create2",
            "function": "users.create",
            "version": "1.0.0",
            "arguments": { "email": "bob@example.com", "name": "Bob" }
          },
          {
            "id": "create3",
            "function": "users.create",
            "version": "1.0.0",
            "arguments": { "email": "invalid-email", "name": "Charlie" }
          }
        ]
      }
    }
  ]
}
```

### Independent Mixed Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_batch_ind",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:batch",
      "data": {
        "mode": "independent",
        "results": [
          {
            "id": "create1",
            "status": 200,
            "result": { "user_id": 101, "email": "alice@example.com" }
          },
          {
            "id": "create2",
            "status": 200,
            "result": { "user_id": 102, "email": "bob@example.com" }
          },
          {
            "id": "create3",
            "status": 400,
            "errors": [{
              "code": "INVALID_ARGUMENTS",
              "message": "Invalid email format"
            }]
          }
        ],
        "summary": {
          "total": 3,
          "succeeded": 2,
          "failed": 1,
          "skipped": 0
        }
      }
    }
  ]
}
```

---

## Limits

Servers SHOULD enforce batch limits:

| Limit | Recommended |
|-------|-------------|
| Max operations | 100 |
| Max payload size | 1 MB |
| Timeout | 60 seconds |

Servers MUST return `BATCH_TOO_LARGE` error when limits exceeded.

---

## Error Codes

| Code | Description |
|------|-------------|
| `BATCH_FAILED` | Atomic batch failed (one or more operations failed) |
| `BATCH_TOO_LARGE` | Too many operations or payload too large |
| `BATCH_TIMEOUT` | Batch execution exceeded timeout |
