---
title: "Issue 6: Context Specification"
---

# Issue 6: Context Specification

> ✅ **FINAL DECISION:** Define standard context fields

---

## Decision

**Define standard context fields with RECOMMENDED status.**

| Field | Type | Status | Description |
|-------|------|--------|-------------|
| `caller` | string | RECOMMENDED | Calling service identifier |
| `request_id` | string | RECOMMENDED | Correlation ID |
| `tenant_id` | string | OPTIONAL | Multi-tenant identifier |
| `user_id` | string | OPTIONAL | End-user identifier |
| `roles` | string[] | OPTIONAL | Authorization roles |
| `locale` | string | OPTIONAL | BCP 47 locale code |

Custom fields use namespaced keys: `myapp.feature_flags`

---

## Original Problem

The `context` field is mentioned but not standardized:

```json
{
  "context": {
    "caller": "checkout-service"
  }
}
```

### What's Missing

1. **No standard fields**: What should go in context?
2. **No schema**: Implementations invent their own
3. **Auth ambiguity**: Should tokens/claims propagate here?
4. **Tracing overlap**: `trace_id` in context vs tracing extension?

### Real-World Needs

Services need to propagate:
- **Identity**: Who is making the request?
- **Tenancy**: Which tenant/organization?
- **Correlation**: How to link related requests?
- **Authorization**: What permissions apply?
- **Locality**: Region, datacenter, zone?

Without standards, every team invents their own:

```json
// Team A
{ "context": { "user_id": "u123", "tenant": "acme" } }

// Team B
{ "context": { "userId": "u123", "organization_id": "acme" } }

// Team C
{ "context": { "principal": { "id": "u123" }, "org": "acme" } }
```

---

## Analysis

### Current Spec Says

From context.md (if it exists) or examples:
- `caller`: Service making the request
- Arbitrary additional fields allowed

### What Other Protocols Do

**gRPC Metadata:**
- Key-value string pairs
- Standard keys: `authorization`, `x-request-id`
- Custom keys prefixed with service name

**OpenTelemetry Baggage:**
- Propagated key-value context
- Standard: `tenant.id`, `user.id`

**HTTP Headers:**
- `Authorization`, `X-Request-ID`, `X-Tenant-ID`
- Custom headers with `X-` prefix

---

## Proposed Solutions

### Option A: Define Standard Fields (Recommended)

Specify common fields with RECOMMENDED status:

```json
{
  "context": {
    "caller": "checkout-service",
    "request_id": "req_abc123",
    "tenant_id": "tenant_acme",
    "user_id": "user_456",
    "roles": ["admin", "billing"],
    "locale": "en-US",
    "region": "us-west-2"
  }
}
```

**Standard Context Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `caller` | string | Service/component making the request |
| `request_id` | string | Correlation ID for request chain |
| `tenant_id` | string | Multi-tenant organization identifier |
| `user_id` | string | End-user identifier (if applicable) |
| `roles` | string[] | Authorization roles/permissions |
| `locale` | string | BCP 47 locale (e.g., "en-US") |
| `region` | string | Geographic region hint |

**Benefits:**
- Consistent across implementations
- Clear expectations
- Interoperability

### Option B: Nested Namespaces

Group related fields:

```json
{
  "context": {
    "identity": {
      "caller": "checkout-service",
      "user_id": "user_456",
      "roles": ["admin"]
    },
    "tenancy": {
      "tenant_id": "acme",
      "environment": "production"
    },
    "correlation": {
      "request_id": "req_abc",
      "causation_id": "req_xyz"
    }
  }
}
```

**Benefits:**
- Organized structure
- Clear separation of concerns

**Drawbacks:**
- More verbose
- Deeper nesting

### Option C: Minimal Core + Extensions

Define minimal core, use extensions for rich context:

**Core context:**
```json
{
  "context": {
    "caller": "checkout-service"
  }
}
```

**Rich context via extension:**
```json
{
  "extensions": [
    {
      "urn": "urn:forrst:ext:identity",
      "options": {
        "user_id": "user_456",
        "roles": ["admin"],
        "tenant_id": "acme"
      }
    }
  ]
}
```

**Benefits:**
- Core stays simple
- Extensions are explicit opt-in

**Drawbacks:**
- Identity feels core, not optional
- More verbose for common case

---

## Recommendation

**Option A (Standard Fields)** with clear tiers:

### Tier 1: Core (SHOULD include)

| Field | Type | When |
|-------|------|------|
| `caller` | string | Always (identifies calling service) |
| `request_id` | string | Always (enables correlation) |

### Tier 2: Multi-Tenant (SHOULD include if applicable)

| Field | Type | When |
|-------|------|------|
| `tenant_id` | string | Multi-tenant systems |
| `user_id` | string | User-initiated requests |

### Tier 3: Optional (MAY include)

| Field | Type | When |
|-------|------|------|
| `roles` | string[] | Authorization needed |
| `locale` | string | Localized responses |
| `region` | string | Geo-routing |
| `environment` | string | env-specific behavior |

### Extensibility

Custom fields SHOULD use namespaced keys:

```json
{
  "context": {
    "caller": "checkout-service",
    "request_id": "req_123",
    "acme.feature_flags": ["new_checkout"],
    "acme.experiment_id": "exp_789"
  }
}
```

---

## Context vs Tracing Extension

**Clarify the boundary:**

| Field | Location | Purpose |
|-------|----------|---------|
| `request_id` | context | Correlation across services |
| `trace_id` | tracing extension | Distributed tracing spans |
| `span_id` | tracing extension | Individual operation |

`request_id` is business correlation; `trace_id` is observability infrastructure.

They MAY be the same value, but serve different purposes.

---

## Actions Required

1. Update `context.md` with standard fields table
2. Add namespaced custom fields documentation
3. Document propagation rules
4. Add examples showing full context usage

## Spec Addition

Add to `context.md`:

```markdown
## Context

The `context` object propagates metadata through call chains.

### Standard Fields

| Field | Type | Status | Description |
|-------|------|--------|-------------|
| `caller` | string | RECOMMENDED | Calling service identifier |
| `request_id` | string | RECOMMENDED | Correlation ID |
| `tenant_id` | string | OPTIONAL | Multi-tenant identifier |
| `user_id` | string | OPTIONAL | End-user identifier |
| `roles` | string[] | OPTIONAL | Authorization roles |
| `locale` | string | OPTIONAL | BCP 47 locale code |

### Custom Fields

Applications MAY add custom fields using namespaced keys:

```json
{
  "context": {
    "caller": "api-gateway",
    "request_id": "req_abc123",
    "myapp.feature_flags": ["beta_checkout"],
    "myapp.experiment_id": "exp_789"
  }
}
```

### Propagation

Downstream services SHOULD propagate received context fields unless security policy forbids it. Servers MAY filter sensitive fields before propagation.

### Context vs Tracing

| Field | Location | Purpose |
|-------|----------|---------|
| `request_id` | context | Business correlation |
| `trace_id` | tracing extension | Observability spans |

They MAY be the same value but serve different purposes.
```
