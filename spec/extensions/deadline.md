---
title: Deadline
description: Request timeouts to prevent cascading failures
---

# Deadline

> Request timeouts to prevent cascading failures

**Extension URN:** `urn:forrst:ext:deadline`

---

## Overview

The deadline extension sets maximum wait times for requests. Deadlines prevent cascading failures by ensuring requests don't wait indefinitely and propagate timeout constraints through call chains.

---

## When to Use

Deadlines SHOULD be used for:
- All production service-to-service calls
- User-facing requests with latency requirements
- Operations with downstream dependencies
- Preventing resource exhaustion from hung requests

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `value` | integer/string | Yes | Duration value or ISO 8601 timestamp |
| `unit` | string | Yes | Time unit |

### Duration Units

**Relative units** (for short timeframes):
- `millisecond` — Milliseconds
- `second` — Seconds
- `minute` — Minutes
- `hour` — Hours

**Absolute units** (for specific points in time):
- `iso8601` — Value is an ISO 8601 timestamp

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `specified` | object | Original deadline from request |
| `elapsed` | object | Time spent processing |
| `remaining` | object | Time remaining when response sent |
| `utilization` | number | Fraction of deadline used (0.0 to 1.0) |

---

## Behavior

When a request includes the deadline extension:

1. Server MUST track remaining time from when request was received
2. Server MUST abandon processing if deadline expires
3. Server MUST return `DEADLINE_EXCEEDED` error when exceeded
4. Server MUST NOT send a response after the deadline (client MAY have given up)

Servers SHOULD:
- Check deadline before starting expensive operations
- Propagate reduced deadline to downstream calls

---

## Deadline Propagation

When Service A calls Service B with a 5 second deadline, and processing takes 1 second before calling C:

```
A ──[5s deadline]──▶ B ──[4s deadline]──▶ C
                     │
                     └─ 1s elapsed
```

Service B SHOULD call C with a 4 second deadline (original minus elapsed time).

---

## Examples

### Request with Relative Deadline

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
      "urn": "urn:forrst:ext:deadline",
      "options": {
        "value": 30,
        "unit": "second"
      }
    }
  ]
}
```

### Request with Absolute Deadline

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_124",
  "call": {
    "function": "reports.generate",
    "version": "1.0.0",
    "arguments": { "type": "quarterly" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:deadline",
      "options": {
        "value": "2024-03-15T14:30:00Z",
        "unit": "iso8601"
      }
    }
  ]
}
```

### Successful Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": {
    "report_url": "https://..."
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:deadline",
      "data": {
        "specified": { "value": 30, "unit": "second" },
        "elapsed": { "value": 127, "unit": "millisecond" },
        "remaining": { "value": 29873, "unit": "millisecond" },
        "utilization": 0.004
      }
    }
  ]
}
```

### Deadline Exceeded Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_125",
  "result": null,
  "errors": [{
    "code": "DEADLINE_EXCEEDED",
    "message": "Request deadline exceeded",
    "details": {
      "deadline": { "value": 30, "unit": "second" },
      "elapsed": { "value": 30001, "unit": "millisecond" }
    }
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:deadline",
      "data": {
        "specified": { "value": 30, "unit": "second" },
        "elapsed": { "value": 30001, "unit": "millisecond" },
        "remaining": { "value": 0, "unit": "millisecond" },
        "utilization": 1.0
      }
    }
  ]
}
```

### Propagated Deadline

When making downstream calls, reduce the deadline by elapsed time:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_downstream",
  "call": {
    "function": "inventory.check",
    "version": "1.0.0",
    "arguments": { "sku": "WIDGET-01" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:deadline",
      "options": {
        "value": 4,
        "unit": "second"
      }
    }
  ]
}
```

---

## Error Codes

| Code | Retryable | Description |
|------|-----------|-------------|
| `DEADLINE_EXCEEDED` | Yes | Request exceeded specified deadline |

---

## Notes

- Use relative units (`second`, `millisecond`) for short durations
- Use `iso8601` for deadlines far in the future
- Clients SHOULD set deadlines on all production calls
- Servers SHOULD check deadline before expensive operations
