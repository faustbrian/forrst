---
title: Tracing
description: Distributed tracing for observability
---

# Tracing

> Distributed tracing for observability

**Extension URN:** `urn:forrst:ext:tracing`

---

## Overview

The tracing extension enables distributed tracing across Forrst calls. Pass correlation IDs through requests and responses to track operations across service boundaries.

---

## When to Use

Tracing SHOULD be used for:
- Microservices architectures
- Debugging cross-service requests
- Performance monitoring
- Request correlation in logs

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `trace_id` | string | Yes | Root trace identifier |
| `span_id` | string | Yes | Current span identifier |
| `parent_span_id` | string | No | Parent span identifier |
| `baggage` | object | No | Key-value pairs propagated across services |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `span_id` | string | Server-generated span ID for this operation |
| `trace_id` | string | Echoed trace ID |
| `duration` | object | Server-side processing duration |

---

## Behavior

When the tracing extension is included:

1. Server MUST propagate `trace_id` to downstream calls
2. Server MUST generate new `span_id` for its operation
3. Server SHOULD use client's `span_id` as `parent_span_id` in downstream calls
4. Server MUST return its `span_id` in response
5. Server SHOULD log with trace context attached

---

## Examples

### Request with Tracing

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "call": {
    "function": "orders.create",
    "version": "1.0.0",
    "arguments": { "product_id": 42, "quantity": 1 }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "options": {
        "trace_id": "abc123def456",
        "span_id": "span_client_001",
        "baggage": {
          "user_tier": "premium",
          "region": "us-west"
        }
      }
    }
  ]
}
```

### Response with Tracing

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": {
    "order_id": "ord_789",
    "status": "created"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "abc123def456",
        "span_id": "span_server_002",
        "duration": { "value": 45, "unit": "millisecond" }
      }
    }
  ]
}
```

### Downstream Propagation

When the server makes downstream calls, it propagates the trace:

```json
{
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "options": {
        "trace_id": "abc123def456",
        "span_id": "span_server_003",
        "parent_span_id": "span_server_002"
      }
    }
  ]
}
```

---

## Integration

### OpenTelemetry Mapping

| Forrst Field | OpenTelemetry |
|------------|---------------|
| `trace_id` | `trace_id` |
| `span_id` | `span_id` |
| `parent_span_id` | `parent_span_id` |
| `baggage` | Baggage |

### W3C Trace Context

Servers MAY support [W3C Trace Context](https://www.w3.org/TR/trace-context/) headers alongside this extension for HTTP transport interoperability. The W3C standard defines `traceparent` and `tracestate` headers for distributed tracing propagation.
