---
title: Document Structure
description: Top-level structure of Forrst requests and responses
---

# Document Structure

> Top-level structure of Forrst requests and responses

---

## Overview

A Forrst document is a JSON object that represents either a request or a response. This document defines the complete structure and member requirements.

---

## Request Document

A request document MUST contain:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_xyz789",
  "call": {
    "function": "orders.get",
    "version": "1.0.0",
    "arguments": {}
  }
}
```

### Top-Level Members

| Member | Type | Required | Description |
|--------|------|----------|-------------|
| `protocol` | string | Yes | Protocol identifier. MUST be `forrst/<major>.<minor>` |
| `id` | string | Yes | Request identifier. MUST be unique per request. |
| `call` | object | Yes | Function invocation details |
| `context` | object | No | Propagated metadata for tracing |
| `extensions` | array | No | Extension objects with `urn` and `options` |

### Call Object

| Member | Type | Required | Description |
|--------|------|----------|-------------|
| `function` | string | Yes | Function name in `<service>.<action>` format |
| `version` | string | No | Function version. Defaults to latest. |
| `arguments` | object | No | Function arguments. Defaults to `{}` |

### Member Ordering

Member order is not significant. Implementations MUST NOT rely on member ordering.

---

## Response Document

A response document MUST contain:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_xyz789",
  "result": { }
}
```

### Top-Level Members

| Member | Type | Required | Description |
|--------|------|----------|-------------|
| `protocol` | string | Yes | Protocol identifier |
| `id` | string | Yes | Echoed request identifier |
| `result` | any | Conditional | Function return value on success |
| `errors` | array | No | Array of error objects on failure |
| `meta` | object | No | Response metadata |
| `extensions` | array | No | Extension objects with `urn` and `data` |

### Result Member

The `result` member contains the function's return value. For functions returning resources, the result SHOULD follow the resource document structure.

**Success states:**
- Object: `{ "order_id": 123 }`
- Array: `[{ "id": 1 }, { "id": 2 }]`
- Resource: `{ "data": { "type": "order", "id": "123", ... } }`
- Collection: `{ "data": [...], "meta": {...} }`
- Scalar: `42`, `"ok"`, `true`
- Null: When function returns nothing

**Error state:**
- `result` MUST be `null` when `error` or `errors` is present

### Error Members

A response MUST satisfy exactly one of:
- **Success:** `result` has value, no `error` or `errors` present
- **Single error:** `result` is `null`, `error` has value, `errors` absent
- **Multiple errors:** `result` is `null`, `error` absent, `errors` has array

The `error` and `errors` fields SHOULD only be present when there is an error. They MUST NOT be included in success responses.

See [Errors](errors.md) for error object structure.

### Response Meta Object (Top-Level)

The top-level `meta` object contains **response-level** metadata that is not specific to the query or business logic:

| Member | Type | Description |
|--------|------|-------------|
| `node` | string | Handling server identifier |
| `rate_limit` | object | Rate limit status |

This metadata relates to how the request was processed, not what was returned.

**Note:** Processing duration is provided via the [Tracing Extension](extensions/tracing.md), not in `meta`.

### Result Meta Object (Inside Result)

The `meta` object inside `result` contains **query-specific** metadata related to the business logic:

| Member | Type | Description |
|--------|------|-------------|
| `pagination` | object | Pagination state (cursors, totals, has_more) |
| `aggregations` | object | Query aggregations/summaries |
| `filters_applied` | array | Which filters were applied |

This metadata relates to the query results, like pagination for collections.

---

## Resource Document

When a function returns a single resource:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": {
    "data": {
      "type": "order",
      "id": "12345",
      "attributes": {
        "status": "pending",
        "total": { "amount": "99.99", "currency": "USD" },
        "created_at": "2024-01-15T10:30:00Z"
      },
      "relationships": {
        "customer": {
          "data": { "type": "customer", "id": "42" }
        },
        "items": {
          "data": [
            { "type": "order_item", "id": "1" },
            { "type": "order_item", "id": "2" }
          ]
        }
      }
    },
    "included": [
      {
        "type": "customer",
        "id": "42",
        "attributes": {
          "name": "Alice",
          "email": "alice@example.com"
        }
      }
    ]
  }
}
```

### Result Members for Resources

| Member | Type | Required | Description |
|--------|------|----------|-------------|
| `data` | object/array/null | Yes | Primary resource(s) |
| `included` | array | No | Related resources (compound document) |
| `meta` | object | No | Non-standard meta-information |

See [Resource Objects](resource-objects.md) for resource structure.

---

## Collection Document

When a function returns multiple resources:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_456",
  "result": {
    "data": [
      {
        "type": "order",
        "id": "12345",
        "attributes": { "status": "pending" }
      },
      {
        "type": "order",
        "id": "12346",
        "attributes": { "status": "shipped" }
      }
    ],
    "included": [],
    "meta": {
      "page": {
        "cursor": {
          "current": "eyJpZCI6MTIzNDV9",
          "prev": null,
          "next": "eyJpZCI6MTIzNDd9"
        }
      },
      "total": 150
    }
  }
}
```

### Collection Meta

| Member | Type | Description |
|--------|------|-------------|
| `page` | object | Pagination cursors and info |
| `total` | integer | Total count (if available) |

---

## Query Arguments

Functions returning collections SHOULD accept standardized query arguments:

```json
{
  "call": {
    "function": "orders.list",
    "version": "1.0.0",
    "arguments": {
      "fields": {
        "self": ["id", "status", "total"],
        "customer": ["id", "name"]
      },
      "filters": [
        {
          "attribute": "status",
          "operator": "equals",
          "value": "pending"
        }
      ],
      "sorts": [
        {
          "attribute": "created_at",
          "direction": "desc"
        }
      ],
      "relationships": ["customer", "items"],
      "pagination": {
        "limit": 25,
        "cursor": "eyJpZCI6MTAwfQ"
      }
    }
  }
}
```

### Query Argument Members

| Member | Type | Description |
|--------|------|-------------|
| `fields` | object | Sparse fieldsets by resource type |
| `filters` | array | Filter criteria |
| `sorts` | array | Sort criteria |
| `relationships` | array | Relationships to include |
| `pagination` | object | Pagination parameters |

See individual specifications:
- [Sparse Fieldsets](extensions/query.md#sparse-fieldsets)
- [Filtering](extensions/query.md#filtering)
- [Sorting](extensions/query.md#sorting)
- [Relationships](extensions/query.md#relationships)
- [Pagination](extensions/query.md#pagination)

---

## Member Naming

### General Rules

All member names MUST:
- Be strings
- Contain at least one character
- Use only allowed characters

Member names SHOULD:
- Use `snake_case` for multi-word names
- Be lowercase
- Be descriptive and unabbreviated

### Reserved Members

These member names are reserved at the top level:
- `protocol`
- `id`
- `call`
- `context`
- `extensions`
- `result`
- `errors`
- `meta`
- `data`
- `included`

### Extension Members

Extensions use structured objects in the `extensions` array:
- Request: `{ "urn": "...", "options": { ... } }`
- Response: `{ "urn": "...", "data": { ... } }`

See [Extensions](extensions/index.md) for full specification.

---

## Document Constraints

### JSON Requirements

- Documents MUST be valid JSON per [RFC 8259](https://www.rfc-editor.org/rfc/rfc8259) (The JavaScript Object Notation Data Interchange Format)
- Documents MUST be encoded as UTF-8
- Documents MUST be a single JSON object (not array)
- Timestamps MUST use [RFC 3339](https://www.rfc-editor.org/rfc/rfc3339) format (ISO 8601 profile)

### Size Limits

Servers SHOULD enforce:
- Maximum request size: 1 MB
- Maximum response size: 10 MB

Servers MUST document their limits via `urn:cline:forrst:fn:capabilities`.

### Null Handling

- Absent members and `null` values have different semantics
- Absent: Member not provided (may use default)
- Null: Explicitly no value

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

### Resource Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_002",
  "call": {
    "function": "orders.get",
    "version": "1.0.0",
    "arguments": {
      "id": "12345",
      "fields": {
        "self": ["id", "status", "total", "created_at"],
        "customer": ["id", "name"]
      },
      "relationships": ["customer"]
    }
  }
}
```

### Resource Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_002",
  "result": {
    "data": {
      "type": "order",
      "id": "12345",
      "attributes": {
        "status": "pending",
        "total": { "amount": "99.99", "currency": "USD" },
        "created_at": "2024-01-15T10:30:00Z"
      },
      "relationships": {
        "customer": {
          "data": { "type": "customer", "id": "42" }
        }
      }
    },
    "included": [
      {
        "type": "customer",
        "id": "42",
        "attributes": {
          "name": "Alice"
        }
      }
    ]
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "tr_abc123",
        "span_id": "span_server_001",
        "duration": { "value": 45, "unit": "millisecond" }
      }
    }
  ]
}
```

### Collection Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_003",
  "call": {
    "function": "orders.list",
    "version": "1.0.0",
    "arguments": {
      "fields": {
        "self": ["id", "status", "total"]
      },
      "filters": [
        {
          "attribute": "status",
          "operator": "in",
          "value": ["pending", "processing"]
        },
        {
          "attribute": "created_at",
          "operator": "greater_than",
          "value": "2024-01-01T00:00:00Z"
        }
      ],
      "sorts": [
        { "attribute": "created_at", "direction": "desc" }
      ],
      "pagination": {
        "limit": 25
      }
    }
  }
}
```

### Collection Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_003",
  "result": {
    "data": [
      {
        "type": "order",
        "id": "12350",
        "attributes": {
          "status": "pending",
          "total": { "amount": "149.99", "currency": "USD" }
        }
      },
      {
        "type": "order",
        "id": "12349",
        "attributes": {
          "status": "processing",
          "total": { "amount": "79.99", "currency": "USD" }
        }
      }
    ],
    "meta": {
      "page": {
        "cursor": {
          "current": "eyJpZCI6MTIzNTB9",
          "prev": null,
          "next": "eyJpZCI6MTIzNDh9"
        }
      }
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "tr_abc123",
        "span_id": "span_server_002",
        "duration": { "value": 89, "unit": "millisecond" }
      }
    }
  ]
}
```

### Error Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_004",
  "result": null,
  "errors": [{
    "code": "NOT_FOUND",
    "message": "Order not found",
    "source": {
      "pointer": "/call/arguments/id"
    }
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "tr_abc123",
        "span_id": "span_server_003",
        "duration": { "value": 12, "unit": "millisecond" }
      }
    }
  ]
}
```
