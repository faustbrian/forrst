---
title: Cancellation
description: Request cancellation for synchronous operations
---

# Cancellation

> Request cancellation for synchronous operations

**Extension URN:** `urn:forrst:ext:cancellation`

---

## Overview

The cancellation extension enables explicit request cancellation for synchronous requests. Clients include a cancellation token in the request and can send a separate cancel request using that token to abort processing.

---

## When to Use

Cancellation SHOULD be used for:
- Long-running synchronous operations
- User-initiated abort (e.g., cancel button)
- Timeout handling on the client side
- Resource cleanup when results are no longer needed

Cancellation SHOULD NOT be used for:
- Async operations (use `urn:cline:forrst:ext:async:fn:cancel` instead)
- Very short operations (overhead exceeds benefit)
- Operations that cannot be safely interrupted

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `token` | string | Yes | Unique cancellation token |

---

## Data (Response)

The cancellation extension does not add response data. Cancelled requests return an error response.

---

## Behavior

### Token Registration

When a request includes the cancellation extension:

1. Server MUST validate the token is a non-empty string
2. Server MUST register the token as active
3. Token MUST remain valid for the request duration
4. Server SHOULD set a TTL on tokens (recommended: 5 minutes)

### Cancellation Flow

```
┌──────────┐     ┌────────────────┐     ┌───────────────┐
│  Client  │     │    Server      │     │   Function    │
└────┬─────┘     └───────┬────────┘     └───────┬───────┘
     │                   │                      │
     │  Request + token  │                      │
     │──────────────────▶│                      │
     │                   │  Register token      │
     │                   │─────────────────────▶│
     │                   │                      │
     │  Cancel request   │                      │
     │──────────────────▶│                      │
     │                   │  Mark cancelled      │
     │                   │─────────────────────▶│
     │                   │                      │
     │                   │  Check cancelled     │
     │                   │◀─────────────────────│
     │                   │                      │
     │  CANCELLED error  │                      │
     │◀──────────────────│                      │
     └───────────────────┴──────────────────────┘
```

### Cancellation Request

To cancel a request, clients call `urn:cline:forrst:ext:cancellation:fn:cancel`:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_cancel",
  "call": {
    "function": "urn:cline:forrst:ext:cancellation:fn:cancel",
    "version": "1.0.0",
    "arguments": {
      "token": "cancel_abc123"
    }
  }
}
```

### Cancellation Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_cancel",
  "result": {
    "cancelled": true,
    "token": "cancel_abc123"
  }
}
```

### Token States

| State | Description |
|-------|-------------|
| `active` | Token registered, request in progress |
| `cancelled` | Cancellation requested |
| (expired) | Token TTL exceeded |

---

## Examples

### Request with Cancellation Token

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
      "urn": "urn:forrst:ext:cancellation",
      "options": {
        "token": "cancel_report_abc123"
      }
    }
  ]
}
```

### Successful Response (Not Cancelled)

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": {
    "report_url": "https://storage.example.com/reports/annual_2024.pdf"
  }
}
```

### Cancelled Response

When the request is cancelled before completion:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [{
    "code": "CANCELLED",
    "message": "Request was cancelled by client",
    "details": {
      "token": "cancel_report_abc123"
    }
  }]
}
```

### Token Not Found

When attempting to cancel an unknown or expired token:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_cancel",
  "result": null,
  "errors": [{
    "code": "CANCELLATION_TOKEN_UNKNOWN",
    "message": "Cancellation token not found or expired",
    "details": {
      "token": "cancel_unknown"
    }
  }]
}
```

### Too Late to Cancel

When the request has already completed:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_cancel",
  "result": null,
  "errors": [{
    "code": "CANCELLATION_TOO_LATE",
    "message": "Request has already completed",
    "details": {
      "token": "cancel_report_abc123"
    }
  }]
}
```

---

## Server Implementation

### Checking Cancellation

Functions SHOULD periodically check if cancellation has been requested:

```php
// In function implementation
if ($this->isCancellationRequested()) {
    $this->throwIfCancellationRequested();
}
```

### Cleanup

Servers MUST clean up tokens after:
- Request completes successfully
- Request fails with error
- Request is cancelled
- Token TTL expires

---

## Error Codes

| Code | Description |
|------|-------------|
| `CANCELLED` | Request was cancelled by client |
| `CANCELLATION_TOKEN_UNKNOWN` | Token not found or expired |
| `CANCELLATION_TOO_LATE` | Request already completed |
| `INVALID_ARGUMENTS` | Token is missing or invalid |
