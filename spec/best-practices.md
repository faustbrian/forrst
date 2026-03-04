---
title: Best Practices
description: Naming conventions and design patterns for Forrst APIs
---

# Best Practices

> Naming conventions and design patterns for Forrst APIs

---

## Naming Conventions

### Function Names

Use `<resource>.<action>` format with clear, predictable verbs:

```
orders.create     ← Create a new order
orders.get        ← Get a single order
orders.list       ← List orders (with filtering)
orders.update     ← Update an order
orders.delete     ← Delete an order
```

### Standard Actions

| Action | Operation | Description |
|--------|-----------|-------------|
| `create` | write | Create a new resource |
| `get` | read | Retrieve a single resource by ID |
| `list` | read | Retrieve multiple resources |
| `update` | write | Modify an existing resource |
| `delete` | delete | Remove a resource |

### Action Variants

For specialized operations, extend the base action:

```
orders.cancel       ← State change (write)
orders.archive      ← State change (write)
orders.duplicate    ← Create variant (write)
orders.validate     ← Validation only (read)
orders.preview      ← Preview result (read)
```

### Read vs Write Distinction

Clients can infer side effects from naming:

**No side effects** (`side_effects: []`):
- `get`, `list` — Standard retrieval
- `search`, `find` — Complex queries
- `validate`, `check` — Validation without side effects
- `preview`, `estimate` — Compute without persisting
- `export` — Bulk read operations

**Has side effects** (`side_effects: ["create"]`, `["update"]`, `["delete"]`):
- `create`, `add` — New resources
- `update`, `set`, `patch` — Modifications
- `delete`, `remove` — Deletions
- Action verbs: `cancel`, `approve`, `archive`, `publish`

### Resource Naming

Use lowercase, plural nouns:

```
orders.list         ✓ Plural
order.list          ✗ Singular

shipping_labels.create   ✓ Snake case
shippingLabels.create    ✗ Camel case
```

### Nested Resources

For resources that belong to a parent, use dots:

```
orders.items.list           ← List items in an order
orders.items.add            ← Add item to order
orders.shipments.create     ← Create shipment for order
```

Arguments include the parent ID:

```json
{
  "call": {
    "function": "orders.items.list",
    "version": "1.0.0",
    "arguments": {
      "order_id": "ord_abc123"
    }
  }
}
```

---

## Argument Design

### ID Arguments

Use explicit, typed ID fields:

```json
// Good: Clear what ID refers to
{
  "arguments": {
    "order_id": "ord_abc123",
    "customer_id": "cus_xyz789"
  }
}

// Avoid: Ambiguous
{
  "arguments": {
    "id": "ord_abc123"
  }
}
```

### Optional Arguments

Group optional arguments logically:

```json
{
  "arguments": {
    "order_id": "ord_abc123",
    "options": {
      "include_items": true,
      "include_customer": false
    }
  }
}
```

### Timestamps

Always use ISO 8601 with timezone:

```json
{
  "created_at": "2024-01-15T10:30:00Z",
  "scheduled_for": "2024-01-20T14:00:00+02:00"
}
```

### Money Values

Use string amounts with explicit currency:

```json
{
  "amount": "99.99",
  "currency": "EUR"
}
```

Or use structured objects:

```json
{
  "price": {
    "amount": "99.99",
    "currency": "EUR"
  }
}
```

---

## Error Handling

### Use Specific Error Codes

```json
// Good: Specific, actionable
{
  "errors": [{
    "code": "INSUFFICIENT_INVENTORY",
    "message": "Product SKU-123 has only 5 units available"
  }]
}

// Avoid: Generic
{
  "errors": [{
    "code": "VALIDATION_ERROR",
    "message": "Invalid request"
  }]
}
```

### Include Remediation Hints

```json
{
  "errors": [{
    "code": "RATE_LIMITED",
    "message": "Too many requests",
    "details": {
      "retry_after": { "value": 30, "unit": "second" },
      "limit": 100,
      "window": { "value": 1, "unit": "minute" }
    }
  }]
}
```

---

## Versioning Strategy

### When to Create New Versions

**Requires new version:**
- Removing required arguments
- Changing argument types
- Removing return fields
- Changing behavior semantics

**Does NOT require new version:**
- Adding optional arguments with defaults
- Adding new return fields
- Bug fixes that match documented behavior
- Performance improvements

### Deprecation Timeline

1. **Announce** — Document deprecation with sunset date
2. **Warn** — Return deprecation warning in responses
3. **Sunset** — Return `VERSION_NOT_FOUND` after sunset

Minimum deprecation period: 6 months for stable versions.

---

## Performance

### Use Pagination

Always paginate list operations:

```json
{
  "arguments": {
    "pagination": {
      "limit": 50,
      "cursor": "eyJpZCI6MTAwfQ"
    }
  }
}
```

### Use Sparse Fieldsets

Request only needed fields:

```json
{
  "arguments": {
    "fields": {
      "self": ["id", "status", "total"],
      "customer": ["id", "name"]
    }
  }
}
```

### Use Deadlines

Set appropriate deadlines for time-sensitive operations:

```json
{
  "extensions": [
    {
      "urn": "urn:forrst:ext:deadline",
      "options": {
        "deadline": "2024-01-15T10:30:05Z"
      }
    }
  ]
}
```

---

## Security

### Validate All Input

Never trust client input. Validate:
- Types and formats
- Ranges and limits
- Authorization for referenced resources

### Use Context for Authorization

Propagate authorization context:

```json
{
  "context": {
    "tenant_id": "tenant_acme",
    "user_id": "user_42",
    "scopes": ["orders:read", "orders:write"]
  }
}
```

### Audit Sensitive Operations

Log context for audit trails:

```json
{
  "context": {
    "caller": "admin-dashboard",
    "user_id": "admin_5",
    "reason": "Customer support ticket #12345"
  }
}
```

---

## Documentation

### Document Every Function

Use `urn:cline:forrst:fn:describe` to provide:
- Clear description
- Schema for arguments and returns
- Operation type
- Deprecation status

### Use Consistent Examples

Show complete request/response pairs in documentation:

```json
// Request
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "call": {
    "function": "orders.get",
    "version": "1.0.0",
    "arguments": {
      "order_id": "ord_abc123"
    }
  }
}

// Response
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "result": {
    "id": "ord_abc123",
    "status": "pending",
    "total": { "amount": "99.99", "currency": "EUR" }
  }
}
```
