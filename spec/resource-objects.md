---
title: Resource Objects
description: Standard structure for representing domain entities
---

# Resource Objects

> Standard structure for representing domain entities

---

## Overview

Resource objects represent domain entities (database records, API resources, etc.) with a consistent structure. This enables predictable data access, relationship handling, and query operations.

---

## Resource Object Structure

A resource object MUST contain:

```json
{
  "type": "order",
  "id": "ord_01JG8Z9QXNB6V9K4PT7YSNWF3M",
  "attributes": {
    "status": "pending",
    "total": { "amount": "99.99", "currency": "USD" },
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

### Required Members

| Member | Type | Description |
|--------|------|-------------|
| `type` | string | Resource type identifier |
| `id` | string | Unique identifier within type |

### Optional Members

| Member | Type | Description |
|--------|------|-------------|
| `attributes` | object | Resource attributes |
| `relationships` | object | Related resources |
| `meta` | object | Non-standard meta-information |

---

## Type Member

The `type` member identifies the resource's type.

### Rules

- MUST be a string
- MUST contain at least one character
- MUST be unique across the API
- SHOULD use `snake_case` for multi-word types
- SHOULD be singular (e.g., `order` not `orders`)
- SHOULD match the domain model name

### Examples

```
order
order_item
shipping_address
tracking_event
customer
```

### Anti-patterns

```
orders          # Plural - use singular
orderItem       # camelCase - use snake_case
Order           # PascalCase - use lowercase
order-item      # kebab-case - use snake_case
```

---

## ID Member

The `id` member uniquely identifies a resource within its type.

### Rules

- MUST be a string
- MUST be unique within the resource type
- MUST NOT change for the lifetime of the resource
- SHOULD be URL-safe

### ID Formats

**Recommended: Stripe-Style Prefixed IDs**

Forrst recommends Stripe-style prefixed ULID identifiers for all resource types:

```
<prefix>_<ulid>
```

Examples:

```
op_01JG8Z9QXNB6V9K4PT7YSNWF3M    # Operation
srv_01JG8Z9QXNB6V9K4PT7YSNWF3M   # Server
func_01JG8Z9QXNB6V9K4PT7YSNWF3M  # Function
res_01JG8Z9QXNB6V9K4PT7YSNWF3M   # Resource
```

**Benefits:**

- **Sortable**: ULIDs encode timestamp, enabling chronological sorting
- **Unique**: Cryptographically random component eliminates collision checks
- **Readable**: Prefix indicates resource type at a glance
- **Compact**: 26-character ULID vs 36-character UUID
- **URL-safe**: No special characters requiring encoding

**Configuration:**

Configure ID generation in `config/rpc.php`:

```php
'extensions' => [
    'async' => [
        'operation_id' => [
            'generator' => 'prefixed',
            'prefix' => 'op',
            'prefixed_generator' => 'ulid',
        ],
    ],
],
```

**Alternative Formats:**

If Stripe-style IDs don't fit your use case, these formats are also supported:

| Format | Example | Notes |
|--------|---------|-------|
| **Stripe-style (recommended)** | `"op_01ARZ3NDEKTSV4RRFFQ69G5FAV"` | Type-prefixed ULID |
| ULID | `"01ARZ3NDEKTSV4RRFFQ69G5FAV"` | Sortable, compact |
| UUID v7 | `"018d3f5a-45e2-7b4a-9c5f-abc123def456"` | Timestamp-based UUID |
| UUID v4 | `"550e8400-e29b-41d4-a716-446655440000"` | Random UUID |
| Integer (as string) | `"12345"` | Database primary keys |

**Type Safety:**

IDs MUST be strings even for numeric values:

```json
// Correct
{ "type": "order", "id": "op_01JG8Z9QXNB6V9K4PT7YSNWF3M" }

// Incorrect
{ "type": "order", "id": 12345 }
```

---

## Attributes Member

The `attributes` member contains the resource's data.

### Rules

- MUST be an object
- MUST NOT contain `id` or `type` (these are top-level)
- MUST NOT contain relationship data
- MAY contain any valid JSON value

### Attribute Naming

Attribute names SHOULD:
- Use `snake_case`
- Be descriptive
- Avoid abbreviations

```json
{
  "type": "order",
  "id": "ord_01JG8Z9QXNB6V9K4PT7YSNWF3M",
  "attributes": {
    "order_number": "ORD-2024-001",
    "status": "pending",
    "total_amount": { "amount": "99.99", "currency": "USD" },
    "item_count": 3,
    "is_gift": false,
    "notes": null,
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:35:00Z"
  }
}
```

### Complex Attributes

Attributes MAY contain nested objects and arrays:

```json
{
  "type": "customer",
  "id": "cus_01JG8Z9QXNB6V9K4PT7YSNWF3M",
  "attributes": {
    "name": "Alice Smith",
    "email": "alice@example.com",
    "address": {
      "line1": "123 Main St",
      "line2": null,
      "city": "Helsinki",
      "postal_code": "00100",
      "country_code": "FI"
    },
    "tags": ["vip", "wholesale"],
    "preferences": {
      "newsletter": true,
      "language": "en"
    }
  }
}
```

### Attribute Types

| JSON Type | Usage |
|-----------|-------|
| string | Text, enums, dates (ISO 8601), UUIDs |
| number | Integers, floats (avoid for currency) |
| boolean | True/false flags |
| null | Absent or unknown values |
| object | Structured data, value objects |
| array | Lists, collections |

### Money Values

Money SHOULD use a structured format to avoid floating-point issues:

```json
{
  "total": {
    "amount": "99.99",
    "currency": "USD"
  }
}
```

### Date/Time Values

Dates and times MUST use ISO 8601 format:

```json
{
  "created_at": "2024-01-15T10:30:00Z",
  "scheduled_date": "2024-01-20",
  "duration": { "value": 30, "unit": "minute" }
}
```

---

## Relationships Member

The `relationships` member describes connections to other resources.

### Structure

```json
{
  "type": "order",
  "id": "ord_01JG8Z9QXNB6V9K4PT7YSNWF3M",
  "attributes": { ... },
  "relationships": {
    "customer": {
      "data": { "type": "customer", "id": "cus_01JG8Z9QXNB6V9K4PT7YSNWF3M" }
    },
    "items": {
      "data": [
        { "type": "order_item", "id": "itm_01JG8Z9QXNB6V9K4PT7YSNWF3N" },
        { "type": "order_item", "id": "itm_01JG8Z9QXNB6V9K4PT7YSNWF3P" }
      ]
    },
    "shipping_address": {
      "data": null
    }
  }
}
```

### Relationship Object

Each relationship MUST contain a `data` member:

| `data` Value | Meaning |
|--------------|---------|
| Object | To-one relationship (resource identifier) |
| Array | To-many relationship (resource identifiers) |
| null | Empty to-one relationship |
| `[]` | Empty to-many relationship |

### Resource Identifier

A resource identifier contains only `type` and `id`:

```json
{ "type": "customer", "id": "cus_01JG8Z9QXNB6V9K4PT7YSNWF3M" }
```

This is NOT a full resource object—it's a reference.

### Relationship Names

Relationship names SHOULD:
- Use `snake_case`
- Be descriptive of the relationship
- Match the related resource type when appropriate

```json
{
  "relationships": {
    "customer": { ... },           // Matches type
    "billing_address": { ... },    // Descriptive
    "shipping_address": { ... },   // Descriptive
    "created_by": { ... },         // Describes relationship
    "items": { ... }               // Plural for to-many
  }
}
```

---

## Compound Documents

When including related resources, use the `included` array:

```json
{
  "data": {
    "type": "order",
    "id": "ord_01JG8Z9QXNB6V9K4PT7YSNWF3M",
    "attributes": {
      "status": "pending"
    },
    "relationships": {
      "customer": {
        "data": { "type": "customer", "id": "cus_01JG8Z9QXNB6V9K4PT7YSNWF3M" }
      }
    }
  },
  "included": [
    {
      "type": "customer",
      "id": "cus_01JG8Z9QXNB6V9K4PT7YSNWF3M",
      "attributes": {
        "name": "Alice",
        "email": "alice@example.com"
      }
    }
  ]
}
```

### Rules

- `included` MUST be an array of resource objects
- Each included resource MUST be unique (by type + id)
- Included resources MUST be referenced by at least one relationship
- Included resources MAY have their own relationships

See [Relationships](extensions/query.md#relationships) for inclusion details.

---

## Meta Member

The `meta` member contains non-standard information:

```json
{
  "type": "order",
  "id": "ord_01JG8Z9QXNB6V9K4PT7YSNWF3M",
  "attributes": { ... },
  "meta": {
    "permissions": ["read", "update"],
    "cache_expires_at": "2024-01-15T11:00:00Z",
    "version": "3.0.0"
  }
}
```

### Use Cases

- Permission indicators
- Cache control hints
- Version numbers
- Computed values not in attributes

---

## Resource Definition

Services SHOULD define their resources with allowed fields, filters, relationships, and sorts:

```php
// Conceptual resource definition
{
  "type": "order",
  "fields": {
    "self": ["id", "order_number", "status", "total_amount", "created_at", "updated_at"],
    "customer": ["id", "name", "email"],
    "items": ["id", "sku", "quantity", "price"]
  },
  "filters": {
    "self": ["id", "order_number", "status", "created_at"],
    "customer": ["id", "name"]
  },
  "sorts": {
    "self": ["order_number", "status", "created_at", "total_amount"]
  },
  "relationships": ["customer", "items", "shipping_address", "billing_address"]
}
```

This definition:
- Whitelists queryable fields
- Whitelists filterable attributes
- Whitelists sortable attributes
- Defines available relationships

---

## Sparse Fieldsets

Clients MAY request specific fields:

```json
{
  "fields": {
    "self": ["id", "status", "total_amount"],
    "customer": ["id", "name"]
  }
}
```

Response includes only requested fields:

```json
{
  "type": "order",
  "id": "ord_01JG8Z9QXNB6V9K4PT7YSNWF3M",
  "attributes": {
    "status": "pending",
    "total_amount": { "amount": "99.99", "currency": "USD" }
  }
}
```

See [Sparse Fieldsets](extensions/query.md#sparse-fieldsets) for details.

---

## Examples

### Simple Resource

```json
{
  "type": "customer",
  "id": "cus_01JG8Z9QXNB6V9K4PT7YSNWF3M",
  "attributes": {
    "name": "Alice Smith",
    "email": "alice@example.com",
    "created_at": "2024-01-10T08:00:00Z"
  }
}
```

### Resource with Relationships

```json
{
  "type": "order",
  "id": "ord_01JG8Z9QXNB6V9K4PT7YSNWF3M",
  "attributes": {
    "order_number": "ORD-2024-001",
    "status": "shipped",
    "total_amount": { "amount": "249.99", "currency": "USD" },
    "created_at": "2024-01-15T10:30:00Z"
  },
  "relationships": {
    "customer": {
      "data": { "type": "customer", "id": "cus_01JG8Z9QXNB6V9K4PT7YSNWF3M" }
    },
    "items": {
      "data": [
        { "type": "order_item", "id": "itm_01JG8Z9QXNB6V9K4PT7YSNWF3N" },
        { "type": "order_item", "id": "itm_01JG8Z9QXNB6V9K4PT7YSNWF3P" }
      ]
    },
    "shipping_address": {
      "data": { "type": "address", "id": "addr_01JG8Z9QXNB6V9K4PT7YSNWF3Q" }
    }
  }
}
```

### Resource with Meta

```json
{
  "type": "document",
  "id": "doc_01JG8Z9QXNB6V9K4PT7YSNWF3M",
  "attributes": {
    "title": "Q4 Report",
    "content_type": "application/pdf",
    "size_bytes": 1048576
  },
  "meta": {
    "download_url": "https://...",
    "expires_at": "2024-01-16T10:30:00Z",
    "permissions": ["read", "download"]
  }
}
```

### Collection of Resources

```json
{
  "data": [
    {
      "type": "tracking_event",
      "id": "evt_01JG8Z9QXNB6V9K4PT7YSNWF3M",
      "attributes": {
        "status": "in_transit",
        "location": "Helsinki Hub",
        "occurred_at": "2024-01-15T14:30:00Z"
      }
    },
    {
      "type": "tracking_event",
      "id": "evt_01JG8Z9QXNB6V9K4PT7YSNWF3N",
      "attributes": {
        "status": "out_for_delivery",
        "location": "Local Depot",
        "occurred_at": "2024-01-15T16:00:00Z"
      }
    }
  ],
  "meta": {
    "page": {
      "cursor": {
        "current": "...",
        "next": null
      }
    }
  }
}
```

### Full Compound Document

```json
{
  "data": {
    "type": "shipment",
    "id": "ship_01JG8Z9QXNB6V9K4PT7YSNWF3M",
    "attributes": {
      "tracking_number": "MH726955185FI",
      "status": "in_transit",
      "carrier": "posti",
      "created_at": "2024-01-14T09:00:00Z"
    },
    "relationships": {
      "origin": {
        "data": { "type": "location", "id": "loc_01JG8Z9QXNB6V9K4PT7YSNWF3N" }
      },
      "destination": {
        "data": { "type": "location", "id": "loc_01JG8Z9QXNB6V9K4PT7YSNWF3P" }
      },
      "events": {
        "data": [
          { "type": "tracking_event", "id": "evt_01JG8Z9QXNB6V9K4PT7YSNWF3Q" },
          { "type": "tracking_event", "id": "evt_01JG8Z9QXNB6V9K4PT7YSNWF3R" }
        ]
      }
    }
  },
  "included": [
    {
      "type": "location",
      "id": "loc_01JG8Z9QXNB6V9K4PT7YSNWF3N",
      "attributes": {
        "name": "Helsinki Warehouse",
        "postal_code": "00100",
        "country_code": "FI"
      }
    },
    {
      "type": "location",
      "id": "loc_01JG8Z9QXNB6V9K4PT7YSNWF3P",
      "attributes": {
        "name": "Tampere Office",
        "postal_code": "33100",
        "country_code": "FI"
      }
    },
    {
      "type": "tracking_event",
      "id": "evt_01JG8Z9QXNB6V9K4PT7YSNWF3Q",
      "attributes": {
        "status": "picked_up",
        "occurred_at": "2024-01-14T10:00:00Z"
      }
    },
    {
      "type": "tracking_event",
      "id": "evt_01JG8Z9QXNB6V9K4PT7YSNWF3R",
      "attributes": {
        "status": "in_transit",
        "occurred_at": "2024-01-15T08:00:00Z"
      }
    }
  ]
}
```
