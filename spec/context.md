---
title: Context
description: Context propagation for metadata across service calls
---

# Context

> Context propagation for metadata across service calls

---

## Overview

The `context` object carries metadata that propagates through the entire call chain. When Service A calls Service B, and B calls Service C, context flows A→B→C.

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Service A  │────▶│  Service B  │────▶│  Service C  │
│             │     │             │     │             │
│ tenant: X   │     │ tenant: X   │     │ tenant: X   │
│ caller: A   │     │ caller: B   │     │ caller: C   │
└─────────────┘     └─────────────┘     └─────────────┘
```

---

## Context Object

```json
{
  "context": {
    "caller": "checkout-service",
    "tenant_id": "tenant_acme",
    "user_id": "user_42"
  }
}
```

### Standard Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `caller` | string | No | Identifier of the calling service |

### Custom Fields

Applications MAY add custom fields to context for domain-specific propagation:

```json
{
  "context": {
    "caller": "checkout-service",
    "tenant_id": "tenant_acme",
    "user_id": "user_42",
    "feature_flags": ["new_checkout", "beta_pricing"],
    "correlation_id": "order_12345"
  }
}
```

Common custom fields:
- `tenant_id` — Multi-tenant isolation
- `user_id` — Acting user (for audit)
- `feature_flags` — Active feature flags
- `correlation_id` — Business correlation
- `environment` — Environment hint (staging, production)

---

## Propagation Rules

### Incoming Requests

When receiving a request, servers MUST:

1. Extract `context` from the request
2. Preserve custom fields unchanged
3. Update `caller` to current service name for downstream calls

### Outgoing Requests

When making downstream calls, clients MUST:

1. Copy custom fields from current context
2. Set `caller` to current service name
3. Propagate custom fields as appropriate

### Example Chain

**Request A→B:**
```json
{
  "context": {
    "caller": "api-gateway",
    "tenant_id": "tenant_acme"
  }
}
```

**Request B→C:**
```json
{
  "context": {
    "caller": "order-service",
    "tenant_id": "tenant_acme"
  }
}
```

---

## Context vs Extensions

Context and extensions serve different purposes:

| Aspect | Context | Extensions |
|--------|---------|------------|
| Propagates | Yes, through call chain | Per-request (may influence downstream) |
| Purpose | Business metadata | Optional capabilities |
| Examples | tenant_id, user_id, caller | deadline, idempotency, tracing |

**Rule of thumb:**
- If downstream services need it automatically → Context
- If it's an optional capability → Extensions

---

## Distributed Tracing

For distributed tracing (`trace_id`, `span_id`, `parent_span_id`), use the [Tracing Extension](extensions/tracing.md). This keeps observability concerns separate from business context.

---

## Missing Context

Servers MUST handle requests without context gracefully:

```json
// Request without context
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "call": {
    "function": "orders.get",
    "version": "1.0.0",
    "arguments": { "order_id": 42 }
  }
}
```

When context is missing:
- Servers SHOULD log a warning
- Servers MUST process the request normally

---

## Examples

### Basic Context

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_basic",
  "call": {
    "function": "orders.create",
    "version": "1.0.0",
    "arguments": { ... }
  },
  "context": {
    "caller": "web-frontend"
  }
}
```

### Multi-Tenant Context

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_multi_tenant",
  "call": {
    "function": "reports.generate",
    "version": "1.0.0",
    "arguments": { ... }
  },
  "context": {
    "caller": "dashboard-service",
    "tenant_id": "tenant_acme_corp",
    "user_id": "user_42",
    "feature_flags": ["new_report_engine"]
  }
}
```

### With Tracing Extension

For full distributed tracing, combine context with the tracing extension:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_traced",
  "call": {
    "function": "orders.create",
    "version": "1.0.0",
    "arguments": { ... }
  },
  "context": {
    "caller": "checkout-service",
    "tenant_id": "tenant_acme"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "options": {
        "trace_id": "tr_abc123def456",
        "span_id": "sp_001"
      }
    }
  ]
}
```
