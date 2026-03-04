---
title: Async Operations
description: Asynchronous operation patterns for long-running tasks
---

# Async Operations

> Asynchronous operation patterns for long-running tasks

**Extension URN:** `urn:forrst:ext:async`

---

## Overview

Async operations allow functions to return immediately while processing continues in the background. Clients poll for completion or receive callbacks.

This extension provides three management functions for operation lifecycle control:

| Function | Description |
|----------|-------------|
| `urn:cline:forrst:ext:async:fn:status` | Check operation status and progress |
| `urn:cline:forrst:ext:async:fn:cancel` | Cancel a pending/processing operation |
| `urn:cline:forrst:ext:async:fn:list` | List operations for the current caller |

These functions are **only available when the async extension is enabled**. Servers not implementing async operations SHOULD NOT register these functions.

---

## When to Use

Async operations SHOULD be used for:
- Report generation
- Bulk data processing
- External API calls with long latency
- Operations exceeding typical deadline

Async operations SHOULD NOT be used for:
- Simple CRUD operations
- Low-latency requirements
- Operations without observable progress

---

## Request Format

Clients MAY request async handling via the async extension:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "call": {
    "function": "reports.generate",
    "version": "1.0.0",
    "arguments": {
      "type": "annual",
      "year": 2024
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:async",
      "options": {
        "preferred": true
      }
    }
  ]
}
```

### Extension Options

| Field | Type | Description |
|-------|------|-------------|
| `preferred` | boolean | Client prefers async if operation is long-running |
| `callback_url` | string | URL to POST result when complete (optional) |

---

## Response Format

### Immediate Async Response

When the server accepts the request for async processing:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
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

### Extension Response Data

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `operation_id` | string | Yes | Unique identifier for the operation |
| `status` | string | Yes | Current status (see below) |
| `poll` | object | Yes | Function call to check status |
| `retry_after` | object | No | Suggested wait before next poll |
| `progress` | number | No | Completion percentage (0.0 to 1.0) |
| `started_at` | string | No | ISO 8601 timestamp when processing started |

### Status Values

| Status | Description |
|--------|-------------|
| `pending` | Accepted but not yet started |
| `processing` | Currently being processed |
| `completed` | Finished successfully |
| `failed` | Finished with error |
| `cancelled` | Cancelled by client or system |

---

## Polling for Status

### Poll Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_poll_1",
  "call": {
    "function": "urn:cline:forrst:ext:async:fn:status",
    "version": "1.0.0",
    "arguments": {
      "operation_id": "op_xyz789"
    }
  }
}
```

### Still Processing Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_poll_1",
  "result": {
    "operation_id": "op_xyz789",
    "status": "processing",
    "progress": 0.45,
    "started_at": "2024-01-15T10:30:00Z",
    "estimated_completion": "2024-01-15T10:31:00Z"
  }
}
```

### Completed Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_poll_2",
  "result": {
    "operation_id": "op_xyz789",
    "status": "completed",
    "output": {
      "report_url": "https://storage.example.com/reports/annual_2024.pdf",
      "generated_at": "2024-01-15T10:31:23Z",
      "page_count": 47
    }
  }
}
```

### Failed Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_poll_3",
  "result": null,
  "errors": [{
    "code": "ASYNC_OPERATION_FAILED",
    "message": "Report generation failed: data source unavailable",
    "details": {
      "operation_id": "op_xyz789",
      "failed_at": "2024-01-15T10:30:45Z",
      "reason": "database_connection_timeout"
    }
  }]
}
```

---

## Callbacks

Instead of polling, clients MAY provide a callback URL:

### Request with Callback

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_callback",
  "call": {
    "function": "reports.generate",
    "version": "1.0.0",
    "arguments": { "type": "annual", "year": 2024 }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:async",
      "options": {
        "preferred": true,
        "callback_url": "https://my-service.example.com/webhooks/forrst"
      }
    }
  ]
}
```

### Callback Payload

When the operation completes, the server MUST POST to the callback URL:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "callback": {
    "operation_id": "op_xyz789",
    "original_request_id": "req_callback",
    "status": "completed",
    "result": {
      "report_url": "https://storage.example.com/reports/annual_2024.pdf"
    },
    "completed_at": "2024-01-15T10:31:23Z"
  }
}
```

### Callback Security

Servers SHOULD:
- Sign callbacks with HMAC or similar
- Include signature in header: `X-Forrst-Signature: sha256=...`
- Support callback URL allowlists

Clients SHOULD:
- Verify callback signatures
- Validate callback source
- Respond with 200 to acknowledge

---

## Cancellation

Clients MAY cancel pending operations:

### Cancel Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_cancel",
  "call": {
    "function": "urn:cline:forrst:ext:async:fn:cancel",
    "version": "1.0.0",
    "arguments": {
      "operation_id": "op_xyz789"
    }
  }
}
```

### Cancel Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_cancel",
  "result": {
    "operation_id": "op_xyz789",
    "status": "cancelled",
    "cancelled_at": "2024-01-15T10:30:30Z"
  }
}
```

### Cannot Cancel

If the operation has already completed or cannot be cancelled:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_cancel",
  "result": null,
  "errors": [{
    "code": "ASYNC_CANNOT_CANCEL",
    "message": "Operation already completed",
    "details": {
      "operation_id": "op_xyz789",
      "status": "completed"
    }
  }]
}
```

---

## Server Behavior

### When to Use Async

Servers SHOULD respond asynchronously when:
- Client specified `options.preferred: true`
- Operation would exceed reasonable time (e.g., > 30s)
- Operation involves external dependencies with high latency

Servers MAY respond synchronously even with `preferred: true` if:
- Operation completes quickly
- Operation is cached

### Operation Lifecycle

```
┌─────────┐     ┌────────────┐     ┌───────────┐
│ pending │────▶│ processing │────▶│ completed │
└─────────┘     └────────────┘     └───────────┘
                      │
                      ▼
                ┌──────────┐
                │  failed  │
                └──────────┘
```

### Operation Storage

Servers SHOULD:
- Store operation state durably
- Set TTL on completed operations (e.g., 24 hours)
- Return `NOT_FOUND` for expired operation IDs

---

## Idempotency

Async operations work with the [Idempotency](idempotency.md) extension:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "call": {
    "function": "reports.generate",
    "version": "1.0.0",
    "arguments": { "type": "annual" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:async",
      "options": {
        "preferred": true
      }
    },
    {
      "urn": "urn:forrst:ext:idempotency",
      "options": {
        "key": "report_annual_2024"
      }
    }
  ]
}
```

If the same idempotency key is used:
- Servers MUST return existing operation ID if still processing
- Servers MUST return completed result if finished

---

## Examples

### Full Async Flow

**1. Initial Request**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_report",
  "call": {
    "function": "reports.generate",
    "version": "1.0.0",
    "arguments": {
      "type": "sales",
      "date_range": {
        "start": "2024-01-01",
        "end": "2024-12-31"
      }
    }
  },
  "context": {
    "trace_id": "tr_report123",
    "span_id": "sp_init"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:async",
      "options": {
        "preferred": true
      }
    }
  ]
}
```

**2. Immediate Response**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_report",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:async",
      "data": {
        "operation_id": "op_sales_report_456",
        "status": "processing",
        "poll": {
          "function": "urn:cline:forrst:ext:async:fn:status",
          "version": "1.0.0",
          "arguments": { "operation_id": "op_sales_report_456" }
        },
        "retry_after": { "value": 10, "unit": "second" }
      }
    }
  ]
}
```

**3. Poll (Still Processing)**

```json
// Request
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_poll_1",
  "call": {
    "function": "urn:cline:forrst:ext:async:fn:status",
    "version": "1.0.0",
    "arguments": { "operation_id": "op_sales_report_456" }
  }
}

// Response
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_poll_1",
  "result": {
    "operation_id": "op_sales_report_456",
    "status": "processing",
    "progress": 0.67,
    "message": "Processing Q3 data..."
  }
}
```

**4. Poll (Complete)**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_poll_2",
  "result": {
    "operation_id": "op_sales_report_456",
    "status": "completed",
    "output": {
      "report_id": "rpt_789",
      "download_url": "https://...",
      "expires_at": "2024-01-16T10:30:00Z",
      "summary": {
        "total_sales": 1250000,
        "record_count": 45000
      }
    }
  }
}
```

---

## Extension Functions

The async extension provides these functions for operation management. They use the reserved `forrst.` namespace but are only available when the async extension is enabled.

### urn:cline:forrst:ext:async:fn:status

Check status of an async operation.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_op_status",
  "call": {
    "function": "urn:cline:forrst:ext:async:fn:status",
    "version": "1.0.0",
    "arguments": {
      "operation_id": "op_xyz789"
    }
  }
}
```

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `operation_id` | string | Yes | Operation ID to check |

**Returns:**

| Field | Type | Description |
|-------|------|-------------|
| `operation_id` | string | Operation identifier |
| `function` | string | Function that was invoked |
| `version` | string | Function version |
| `status` | string | Current status (see Status Values) |
| `progress` | number | Completion percentage (0.0 to 1.0) |
| `result` | any | Function result (when completed) |
| `errors` | array | Error details (when failed) |
| `started_at` | string | ISO 8601 timestamp when processing started |
| `completed_at` | string | ISO 8601 timestamp when finished |

**Errors:**

| Code | Description |
|------|-------------|
| `ASYNC_OPERATION_NOT_FOUND` | Operation ID does not exist or has expired |

---

### urn:cline:forrst:ext:async:fn:cancel

Cancel a pending or processing async operation.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_op_cancel",
  "call": {
    "function": "urn:cline:forrst:ext:async:fn:cancel",
    "version": "1.0.0",
    "arguments": {
      "operation_id": "op_xyz789"
    }
  }
}
```

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `operation_id` | string | Yes | Operation ID to cancel |

**Returns:**

| Field | Type | Description |
|-------|------|-------------|
| `operation_id` | string | Operation ID |
| `status` | string | New status (MUST be `cancelled`) |
| `cancelled_at` | string | ISO 8601 timestamp |

**Errors:**

| Code | Description |
|------|-------------|
| `ASYNC_OPERATION_NOT_FOUND` | Operation ID does not exist |
| `ASYNC_CANNOT_CANCEL` | Operation already completed or cannot be cancelled |

---

### urn:cline:forrst:ext:async:fn:list

List operations for the current caller.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_op_list",
  "call": {
    "function": "urn:cline:forrst:ext:async:fn:list",
    "version": "1.0.0",
    "arguments": {
      "status": "processing",
      "limit": 10
    }
  }
}
```

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `status` | string | No | Filter by status |
| `function` | string | No | Filter by function name |
| `limit` | integer | No | Max results (default 50) |
| `cursor` | string | No | Pagination cursor |

**Returns:**

```json
{
  "operations": [
    {
      "id": "op_abc123",
      "function": "reports.generate",
      "version": "1.0.0",
      "status": "processing",
      "progress": 0.45,
      "started_at": "2024-01-15T10:30:00Z"
    }
  ],
  "next_cursor": "eyJpZCI6Im9wX2RlZjQ1NiJ9"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `operations` | array | Array of operation summaries |
| `next_cursor` | string | Cursor for next page (null if no more) |
