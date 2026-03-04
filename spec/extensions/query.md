---
title: Query
description: Resource querying with filtering, sorting, pagination, and relationships
---

# Query

> Resource querying with filtering, sorting, pagination, and relationships

**Extension URN:** `urn:forrst:ext:query`

---

## Overview

The query extension enables rich querying capabilities for collection and resource functions. It bundles filtering, sorting, pagination, sparse fieldsets, and relationship inclusion into a cohesive package.

When enabled, responses MUST use the [Resource Object](../resource-objects.md) format (`type`/`id`/`attributes` structure) since query features depend on this structure.

---

## When to Use

Query SHOULD be used for:
- CRUD-style resource APIs
- Collection endpoints returning lists of entities
- APIs requiring filtering, sorting, or pagination
- Graph-like data with relationships between resources

Query SHOULD NOT be used for:
- Simple RPC functions returning scalar values
- Action-oriented endpoints (e.g., `payments.process`)
- Functions with custom response structures

---

## Capabilities

The query extension provides five capabilities:

| Capability | Description |
|------------|-------------|
| [Filtering](#filtering) | Query resources by attribute conditions |
| [Sorting](#sorting) | Order results by attributes |
| [Pagination](#pagination) | Navigate large result sets |
| [Sparse Fieldsets](#sparse-fieldsets) | Request only needed fields |
| [Relationships](#relationships) | Include related resources |

---

## Filtering

Filtering allows clients to request a subset of resources matching specific criteria. Filters are applied server-side before returning results.

### Filter Object

A filter specifies a single condition:

```json
{
  "attribute": "status",
  "operator": "equals",
  "value": "pending"
}
```

#### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `attribute` | string | Yes | Attribute to filter on |
| `operator` | string | Yes | Comparison operator |
| `value` | any | Conditional | Value to compare (type depends on operator) |
| `boolean` | string | No | Boolean combinator (`and`, `or`) |

### Operators

#### Equality Operators

| Operator | SQL Equivalent | Value Type | Description |
|----------|---------------|------------|-------------|
| `equals` | `=` | any | Exact match |
| `not_equals` | `!=` | any | Not equal |

```json
{ "attribute": "status", "operator": "equals", "value": "active" }
{ "attribute": "type", "operator": "not_equals", "value": "draft" }
```

#### Comparison Operators

| Operator | SQL Equivalent | Value Type | Description |
|----------|---------------|------------|-------------|
| `greater_than` | `>` | number/string | Greater than |
| `greater_than_or_equal_to` | `>=` | number/string | Greater than or equal |
| `less_than` | `<` | number/string | Less than |
| `less_than_or_equal_to` | `<=` | number/string | Less than or equal |

```json
{ "attribute": "amount", "operator": "greater_than", "value": 100 }
{ "attribute": "created_at", "operator": "greater_than_or_equal_to", "value": "2024-01-01T00:00:00Z" }
```

#### Pattern Operators

| Operator | SQL Equivalent | Value Type | Description |
|----------|---------------|------------|-------------|
| `like` | `LIKE` | string | Pattern match (use `%` wildcard) |
| `not_like` | `NOT LIKE` | string | Negative pattern match |

```json
{ "attribute": "email", "operator": "like", "value": "%@example.com" }
{ "attribute": "name", "operator": "like", "value": "John%" }
{ "attribute": "code", "operator": "not_like", "value": "TEST%" }
```

#### Set Operators

| Operator | SQL Equivalent | Value Type | Description |
|----------|---------------|------------|-------------|
| `in` | `IN` | array | Value in set |
| `not_in` | `NOT IN` | array | Value not in set |

```json
{ "attribute": "status", "operator": "in", "value": ["pending", "processing", "shipped"] }
{ "attribute": "country_code", "operator": "not_in", "value": ["US", "CA"] }
```

#### Range Operators

| Operator | SQL Equivalent | Value Type | Description |
|----------|---------------|------------|-------------|
| `between` | `BETWEEN` | array[2] | Value in range (inclusive) |
| `not_between` | `NOT BETWEEN` | array[2] | Value outside range |

```json
{ "attribute": "price", "operator": "between", "value": [10, 100] }
{ "attribute": "created_at", "operator": "between", "value": ["2024-01-01", "2024-01-31"] }
```

#### Null Operators

| Operator | SQL Equivalent | Value Type | Description |
|----------|---------------|------------|-------------|
| `is_null` | `IS NULL` | — | Attribute is null |
| `is_not_null` | `IS NOT NULL` | — | Attribute is not null |

```json
{ "attribute": "deleted_at", "operator": "is_null" }
{ "attribute": "verified_at", "operator": "is_not_null" }
```

Note: `value` is not required for null operators.

### Filter Structure

Filters are organized by resource using an object:

```json
{
  "filters": {
    "self": [
      { "attribute": "status", "operator": "equals", "value": "active" },
      { "attribute": "created_at", "operator": "greater_than", "value": "2024-01-01T00:00:00Z" }
    ]
  }
}
```

The `self` key targets the primary resource. Additional keys target relationships.

#### Default Behavior

Without explicit `boolean`, filters are combined with `AND`:

```json
{
  "filters": {
    "self": [
      { "attribute": "status", "operator": "equals", "value": "active" },
      { "attribute": "type", "operator": "equals", "value": "premium" }
    ]
  }
}
// SQL: WHERE status = 'active' AND type = 'premium'
```

### Boolean Logic

#### AND Conditions

Explicit AND (same as default):

```json
{
  "filters": {
    "self": [
      { "attribute": "status", "operator": "equals", "value": "active", "boolean": "and" },
      { "attribute": "verified", "operator": "equals", "value": true, "boolean": "and" }
    ]
  }
}
// SQL: WHERE status = 'active' AND verified = true
```

#### OR Conditions

```json
{
  "filters": {
    "self": [
      { "attribute": "status", "operator": "equals", "value": "pending", "boolean": "or" },
      { "attribute": "status", "operator": "equals", "value": "processing", "boolean": "or" }
    ]
  }
}
// SQL: WHERE status = 'pending' OR status = 'processing'
```

Note: For simple OR on same attribute, prefer `in` operator:

```json
{ "attribute": "status", "operator": "in", "value": ["pending", "processing"] }
```

#### Mixed Boolean

Filters are applied sequentially, with each filter's `boolean` determining how it connects to the previous condition:

```json
{
  "filters": {
    "self": [
      { "attribute": "type", "operator": "equals", "value": "order" },
      { "attribute": "status", "operator": "equals", "value": "pending", "boolean": "or" },
      { "attribute": "status", "operator": "equals", "value": "failed", "boolean": "or" }
    ]
  }
}
// SQL: WHERE type = 'order' OR status = 'pending' OR status = 'failed'
```

**Important:** Boolean operators connect filters left-to-right without automatic grouping. For complex grouping logic, use the `in` operator:

```json
{
  "filters": {
    "self": [
      { "attribute": "type", "operator": "equals", "value": "order" },
      { "attribute": "status", "operator": "in", "value": ["pending", "failed"] }
    ]
  }
}
// SQL: WHERE type = 'order' AND status IN ('pending', 'failed')
```

### Filtering by Resource

Filters can target different resources in a query:

```json
{
  "filters": {
    "self": [
      { "attribute": "status", "operator": "equals", "value": "active" }
    ],
    "customer": [
      { "attribute": "country_code", "operator": "equals", "value": "FI" }
    ]
  }
}
```

#### Structure

| Key | Description |
|-----|-------------|
| `self` | Filters on the primary resource |
| `<relationship>` | Filters on related resources |

#### Relationship Filtering

Filtering by relationship creates a `WHERE EXISTS` condition:

```json
{
  "filters": {
    "self": [
      { "attribute": "status", "operator": "equals", "value": "pending" }
    ],
    "customer": [
      { "attribute": "type", "operator": "equals", "value": "vip" }
    ]
  }
}
// SQL: WHERE status = 'pending'
//      AND EXISTS (SELECT 1 FROM customers WHERE customers.id = orders.customer_id AND type = 'vip')
```

### Allowed Filters

Servers MUST define which attributes are filterable per resource:

```json
{
  "filters": {
    "self": ["id", "status", "created_at", "total_amount"],
    "customer": ["id", "type", "country_code"]
  }
}
```

#### Validation

- Filtering on non-allowed attributes MUST return an error
- Servers SHOULD expose allowed filters via `urn:cline:forrst:fn:describe`

### Type Coercion

#### String Values

Most operators accept string values:

```json
{ "attribute": "status", "operator": "equals", "value": "pending" }
```

#### Numeric Values

Comparison operators work with numbers:

```json
{ "attribute": "quantity", "operator": "greater_than", "value": 10 }
{ "attribute": "price", "operator": "between", "value": [10.00, 99.99] }
```

#### Boolean Values

```json
{ "attribute": "is_active", "operator": "equals", "value": true }
{ "attribute": "verified", "operator": "equals", "value": false }
```

#### Date/Time Values

Use ISO 8601 format:

```json
{ "attribute": "created_at", "operator": "greater_than", "value": "2024-01-15T10:30:00Z" }
{ "attribute": "date", "operator": "between", "value": ["2024-01-01", "2024-12-31"] }
```

#### Null Values

Use null operators, not `equals` with `null`:

```json
// Correct
{ "attribute": "deleted_at", "operator": "is_null" }

// Incorrect
{ "attribute": "deleted_at", "operator": "equals", "value": null }
```

---

## Sorting

Sorting allows clients to specify the order of resources in collection responses. Multiple sort criteria are applied in sequence.

### Sort Object

A sort specifies ordering for a single attribute:

```json
{
  "attribute": "created_at",
  "direction": "desc"
}
```

#### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `attribute` | string | Yes | Attribute to sort by |
| `direction` | string | Yes | Sort direction |

#### Direction Values

| Value | SQL Equivalent | Description |
|-------|---------------|-------------|
| `asc` | `ASC` | Ascending (smallest first) |
| `desc` | `DESC` | Descending (largest first) |

### Sort Arrays

Multiple sorts create compound ordering:

```json
{
  "sorts": [
    { "attribute": "status", "direction": "asc" },
    { "attribute": "created_at", "direction": "desc" }
  ]
}
```

#### Order of Application

Sorts are applied in array order:

```json
{
  "sorts": [
    { "attribute": "country_code", "direction": "asc" },
    { "attribute": "city", "direction": "asc" },
    { "attribute": "name", "direction": "asc" }
  ]
}
// SQL: ORDER BY country_code ASC, city ASC, name ASC
```

Primary sort is first, then secondary, etc.

### Allowed Sorts

Servers MUST define which attributes are sortable:

```json
{
  "sorts": {
    "self": ["name", "created_at", "updated_at", "status", "total_amount"]
  }
}
```

#### Validation

- Sorting on non-allowed attributes MUST return an error
- Servers SHOULD expose allowed sorts via `urn:cline:forrst:fn:describe`

### Default Sorting

#### Server Defaults

When no sort is specified, servers SHOULD apply a default sort:

- By primary key (`id`) ascending
- By creation timestamp (`created_at`) descending
- By a natural ordering attribute

Servers MUST document their default sort behavior.

#### Stable Sorting

For pagination stability, servers SHOULD include a unique attribute (like `id`) as the final sort criterion, even if not explicitly requested:

```json
// Client requests
{ "sorts": [{ "attribute": "status", "direction": "asc" }] }

// Server applies (for stability)
// ORDER BY status ASC, id ASC
```

### Sort Direction Semantics

#### Ascending (`asc`)

| Type | Order |
|------|-------|
| Numbers | 1, 2, 3, 10, 100 |
| Strings | A, B, C, a, b, c (locale-dependent) |
| Dates | Oldest first |
| Booleans | false, true |
| Nulls | First or last (implementation-defined) |

#### Descending (`desc`)

| Type | Order |
|------|-------|
| Numbers | 100, 10, 3, 2, 1 |
| Strings | c, b, a, C, B, A (locale-dependent) |
| Dates | Newest first |
| Booleans | true, false |
| Nulls | First or last (implementation-defined) |

#### Null Handling

Servers SHOULD document null ordering behavior:

| Behavior | Description |
|----------|-------------|
| `nulls_first` | Nulls sort before non-null values |
| `nulls_last` | Nulls sort after non-null values |

---

## Pagination

Functions returning lists SHOULD support pagination to handle large result sets efficiently. Forrst defines three pagination styles that cover common use cases.

### Pagination Object

Pagination parameters are passed in the extension options:

```json
{
  "extensions": [
    {
      "urn": "urn:forrst:ext:query",
      "options": {
        "pagination": {
          "limit": 25,
          "cursor": "eyJpZCI6MTAwfQ"
        }
      }
    }
  ]
}
```

### Pagination Styles

#### 1. Offset-Based

Simple pagination using `limit` and `offset`. Familiar from SQL.

**Request:**

```json
{
  "pagination": {
    "limit": 25,
    "offset": 0
  }
}
```

**Response:**

```json
{
  "result": {
    "data": [...],
    "meta": {
      "pagination": {
        "limit": 25,
        "offset": 0,
        "total": 150,
        "has_more": true
      }
    }
  }
}
```

##### Fields

| Field | Type | Description |
|-------|------|-------------|
| `limit` | integer | Maximum items to return |
| `offset` | integer | Number of items to skip |
| `total` | integer | Total items available (optional) |
| `has_more` | boolean | Whether more items exist |

##### Trade-offs

- Simple to implement and understand
- Allows "jump to page N"
- Performance degrades with large offsets
- Results can shift if data changes between requests

**Use when:** Small datasets, admin interfaces, or when random page access is needed.

#### 2. Cursor-Based

Opaque cursor token that encodes position. Server generates cursor, client passes it back.

**First Request:**

```json
{
  "pagination": {
    "limit": 25
  }
}
```

**First Response:**

```json
{
  "result": {
    "data": [...],
    "meta": {
      "pagination": {
        "limit": 25,
        "next_cursor": "eyJpZCI6MTAwLCJkaXIiOiJuZXh0In0",
        "prev_cursor": null,
        "has_more": true
      }
    }
  }
}
```

**Subsequent Request:**

```json
{
  "pagination": {
    "limit": 25,
    "cursor": "eyJpZCI6MTAwLCJkaXIiOiJuZXh0In0"
  }
}
```

##### Fields

| Field | Type | Description |
|-------|------|-------------|
| `limit` | integer | Maximum items to return |
| `cursor` | string | Opaque cursor from previous response |
| `next_cursor` | string | Cursor for next page (null if none) |
| `prev_cursor` | string | Cursor for previous page (null if none) |
| `has_more` | boolean | Whether more items exist in this direction |

##### Cursor Encoding

Cursors SHOULD be:
- Opaque to clients (implementation detail)
- URL-safe (base64url encoded)
- Tamper-resistant (optionally signed)

Example cursor payload (before encoding):
```json
{
  "id": 100,
  "created_at": "2024-01-15T10:30:00Z",
  "dir": "next"
}
```

##### Trade-offs

- Efficient for any position (no offset scanning)
- Stable during concurrent modifications
- Cannot jump to arbitrary page
- Cursor may expire or become invalid

**Use when:** Large datasets, infinite scroll, or when consistency matters.

#### 3. Keyset-Based (Time/ID)

Pagination using explicit field values. Ideal for feeds and timelines.

**Request (ID-based):**

```json
{
  "pagination": {
    "limit": 25,
    "after_id": "msg_abc123",
    "before_id": null
  }
}
```

**Request (Time-based):**

```json
{
  "pagination": {
    "limit": 25,
    "since": "2024-01-15T10:00:00Z",
    "until": "2024-01-15T12:00:00Z"
  }
}
```

**Response:**

```json
{
  "result": {
    "data": [...],
    "meta": {
      "pagination": {
        "limit": 25,
        "newest_id": "msg_xyz789",
        "oldest_id": "msg_abc124",
        "has_newer": true,
        "has_older": true
      }
    }
  }
}
```

##### Fields (Request)

| Field | Type | Description |
|-------|------|-------------|
| `limit` | integer | Maximum items to return |
| `after_id` | string | Return items after this ID (exclusive) |
| `before_id` | string | Return items before this ID (exclusive) |
| `since` | string | Return items after this timestamp (ISO 8601) |
| `until` | string | Return items before this timestamp (ISO 8601) |

##### Fields (Response)

| Field | Type | Description |
|-------|------|-------------|
| `newest_id` | string | ID of newest item in response |
| `oldest_id` | string | ID of oldest item in response |
| `has_newer` | boolean | Whether newer items exist |
| `has_older` | boolean | Whether older items exist |

##### Combining Parameters

- `after_id` + `limit` — Get next N items after ID
- `before_id` + `limit` — Get previous N items before ID
- `since` + `until` — Get items in time range
- `since` + `limit` — Get N items since timestamp

##### Trade-offs

- Perfect for polling ("give me everything since last fetch")
- Efficient keyset queries
- Requires monotonic IDs or indexed timestamps
- More complex query logic

**Use when:** Activity feeds, timelines, event logs, or polling scenarios.

### Server Implementation

#### Choosing a Style

Functions MAY support one or more pagination styles. Servers SHOULD:

1. Document which style(s) each function supports
2. Return an error if unsupported pagination parameters are provided
3. Apply sensible defaults when pagination is omitted

#### Default Limits

Servers SHOULD:
- Define a default `limit` (e.g., 25)
- Define a maximum `limit` (e.g., 100)
- Return an error if requested `limit` exceeds maximum

#### Empty Results

When no items match:

```json
{
  "result": {
    "data": [],
    "meta": {
      "pagination": {
        "limit": 25,
        "has_more": false
      }
    }
  }
}
```

### Client Behavior

#### Iterating Pages

Cursor-based example:

```
cursor = null
do {
    response = call("orders.list", {
        extensions: [{
            urn: "urn:forrst:ext:query",
            options: { pagination: { limit: 25, cursor: cursor } }
        }]
    })
    process(response.result.data)
    cursor = response.result.meta.pagination.next_cursor
} while (response.result.meta.pagination.has_more)
```

#### Polling for Updates

Keyset-based example:

```
last_id = null
loop {
    response = call("events.list", {
        extensions: [{
            urn: "urn:forrst:ext:query",
            options: { pagination: { limit: 100, after_id: last_id } }
        }]
    })
    if (response.result.data.length > 0) {
        process(response.result.data)
        last_id = response.result.meta.pagination.newest_id
    }
    sleep(interval)
}
```

---

## Sparse Fieldsets

Sparse fieldsets allow clients to request a subset of attributes for each resource type. This reduces payload size and improves performance by excluding unnecessary data.

### Fields Object

The `fields` object specifies which attributes to include per resource type:

```json
{
  "fields": {
    "self": ["id", "status", "total_amount", "created_at"],
    "customer": ["id", "name"]
  }
}
```

#### Structure

| Key | Description |
|-----|-------------|
| `self` | Fields for the primary resource |
| `<relationship>` | Fields for related resources |

#### Rules

- Keys are resource type identifiers
- Values are arrays of attribute names
- `id` and `type` are always included (not specified in fieldset)
- Empty array `[]` returns only `type` and `id`
- Absent key returns all fields for that type

### Request Format

```json
{
  "extensions": [
    {
      "urn": "urn:forrst:ext:query",
      "options": {
        "fields": {
          "self": ["id", "status", "total_amount"],
          "customer": ["id", "name", "email"]
        },
        "relationships": ["customer"]
      }
    }
  ]
}
```

### Response Format

Response includes only requested fields:

```json
{
  "result": {
    "data": {
      "type": "order",
      "id": "12345",
      "attributes": {
        "status": "pending",
        "total_amount": { "amount": "99.99", "currency": "USD" }
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
          "name": "Alice",
          "email": "alice@example.com"
        }
      }
    ]
  }
}
```

Note: `type` and `id` are always present even when not in the fieldset.

### Allowed Fields

Servers MUST define which fields are queryable per resource:

```json
{
  "fields": {
    "self": ["id", "order_number", "status", "total_amount", "created_at", "updated_at"],
    "customer": ["id", "name", "email", "type"],
    "items": ["id", "sku", "name", "quantity", "price"]
  }
}
```

#### Validation

- Requesting non-allowed fields MUST return an error
- Servers SHOULD expose allowed fields via `urn:cline:forrst:fn:describe`

### Default Behavior

#### No Fields Specified

When `fields` is absent, servers return all allowed fields:

```json
// Request without fields option
{
  "extensions": [{
    "urn": "urn:forrst:ext:query",
    "options": {}
  }]
}

// Response includes all fields
{
  "data": {
    "type": "order",
    "id": "12345",
    "attributes": {
      "order_number": "ORD-2024-001",
      "status": "pending",
      "total_amount": { "amount": "99.99", "currency": "USD" },
      "item_count": 3,
      "notes": null,
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:35:00Z"
    }
  }
}
```

#### Empty Fieldset

Empty array returns only `type` and `id`:

```json
// Request
{
  "fields": {
    "self": []
  }
}

// Response
{
  "data": {
    "type": "order",
    "id": "12345"
  }
}
```

### Field Selection Patterns

#### Minimal Response

Request only identifiers:

```json
{
  "fields": {
    "self": []
  }
}
```

#### Summary View

Request key fields for list views:

```json
{
  "fields": {
    "self": ["id", "status", "total_amount", "created_at"]
  }
}
```

#### Detail View

Request all fields including relationships:

```json
{
  "fields": {
    "self": ["id", "order_number", "status", "total_amount", "notes", "created_at", "updated_at"],
    "customer": ["id", "name", "email"],
    "items": ["id", "sku", "name", "quantity", "price"]
  },
  "relationships": ["customer", "items"]
}
```

---

## Relationships

Relationships define connections between resources. Forrst supports requesting related resources in a single call, reducing round trips and enabling efficient data fetching.

### Requesting Relationships

Use the `relationships` array to include related resources:

```json
{
  "extensions": [
    {
      "urn": "urn:forrst:ext:query",
      "options": {
        "relationships": ["customer", "items"]
      }
    }
  ]
}
```

#### Rules

- `relationships` is an array of relationship names
- Related resources are returned in the `included` array
- Only declared relationships can be requested
- Order in the array does not affect response

### Response Structure

#### Relationship Data

Each relationship contains a `data` member with resource identifiers:

```json
{
  "data": {
    "type": "order",
    "id": "12345",
    "attributes": { ... },
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
  }
}
```

#### Relationship Types

| Type | `data` Value | Description |
|------|-------------|-------------|
| To-one | `{ "type": "...", "id": "..." }` | Single related resource |
| To-many | `[{ "type": "...", "id": "..." }, ...]` | Multiple related resources |
| Empty to-one | `null` | No related resource |
| Empty to-many | `[]` | No related resources |

#### Included Resources

When relationships are requested, full resource objects appear in `included`:

```json
{
  "result": {
    "data": {
      "type": "order",
      "id": "12345",
      "attributes": { "status": "pending" },
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
          "name": "Alice",
          "email": "alice@example.com"
        }
      }
    ]
  }
}
```

### Compound Documents

A compound document contains the primary resource(s) plus related resources.

#### Structure

```json
{
  "result": {
    "data": { ... },      // Primary resource(s)
    "included": [ ... ],  // Related resources
    "meta": { ... }       // Optional metadata
  }
}
```

#### Rules

1. Each resource in `included` MUST be unique by `type` + `id`
2. Resources in `included` MUST be referenced by at least one relationship
3. `included` resources MAY have their own relationships
4. Circular references are allowed (resource A → B → A)

#### Deduplication

When multiple resources reference the same related resource, it appears once in `included`:

```json
{
  "data": [
    {
      "type": "order",
      "id": "1",
      "relationships": {
        "customer": { "data": { "type": "customer", "id": "42" } }
      }
    },
    {
      "type": "order",
      "id": "2",
      "relationships": {
        "customer": { "data": { "type": "customer", "id": "42" } }
      }
    }
  ],
  "included": [
    {
      "type": "customer",
      "id": "42",
      "attributes": { "name": "Alice" }
    }
    // Customer 42 appears only once
  ]
}
```

### Nested Relationships

Request relationships of relationships using dot notation:

```json
{
  "relationships": ["customer", "items", "items.product"]
}
```

This includes:
- `customer` - The order's customer
- `items` - The order's line items
- `items.product` - Each item's product

#### Response

```json
{
  "data": {
    "type": "order",
    "id": "12345",
    "relationships": {
      "customer": { "data": { "type": "customer", "id": "42" } },
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
      "attributes": { "name": "Alice" }
    },
    {
      "type": "order_item",
      "id": "1",
      "attributes": { "quantity": 2, "price": { "amount": "29.99", "currency": "USD" } },
      "relationships": {
        "product": { "data": { "type": "product", "id": "prod_abc" } }
      }
    },
    {
      "type": "order_item",
      "id": "2",
      "attributes": { "quantity": 1, "price": { "amount": "49.99", "currency": "USD" } },
      "relationships": {
        "product": { "data": { "type": "product", "id": "prod_xyz" } }
      }
    },
    {
      "type": "product",
      "id": "prod_abc",
      "attributes": { "name": "Widget", "sku": "WDG-001" }
    },
    {
      "type": "product",
      "id": "prod_xyz",
      "attributes": { "name": "Gadget", "sku": "GDG-002" }
    }
  ]
}
```

#### Depth Limits

Servers SHOULD enforce a maximum nesting depth (e.g., 3 levels):

```json
// Allowed
"items.product.category"

// May be rejected
"items.product.category.parent.parent"
```

### Allowed Relationships

Servers MUST define which relationships are available:

```json
{
  "relationships": ["customer", "items", "shipping_address", "billing_address"]
}
```

#### Validation

- Requesting non-allowed relationships MUST return an error
- Servers SHOULD expose allowed relationships via `urn:cline:forrst:fn:describe`

### Relationship Filtering

Filter the primary resource based on related resource attributes:

```json
{
  "filters": {
    "self": [
      { "attribute": "status", "operator": "equals", "value": "pending" }
    ],
    "customer": [
      { "attribute": "type", "operator": "equals", "value": "vip" }
    ]
  },
  "relationships": ["customer"]
}
```

This returns orders that:
1. Have status "pending"
2. Have a customer with type "vip"

#### SQL Equivalent

```sql
SELECT orders.* FROM orders
JOIN customers ON customers.id = orders.customer_id
WHERE orders.status = 'pending'
AND customers.type = 'vip'
```

### Relationship Fields

Control which fields are returned for related resources:

```json
{
  "fields": {
    "self": ["id", "status", "total_amount"],
    "customer": ["id", "name"],
    "items": ["id", "quantity", "price"]
  },
  "relationships": ["customer", "items"]
}
```

### Without Inclusion

Request relationship data without full resources:

```json
// Request without relationships option - identifiers only
{
  "extensions": [{
    "urn": "urn:forrst:ext:query",
    "options": {}
  }]
}

// Response includes relationship identifiers but no included array
{
  "data": {
    "type": "order",
    "id": "12345",
    "attributes": { ... },
    "relationships": {
      "customer": {
        "data": { "type": "customer", "id": "42" }
      }
    }
  }
  // No "included" - only identifiers
}
```

This allows clients to:
- See what relationships exist
- Fetch related resources separately if needed
- Reduce payload when relationships aren't needed

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `fields` | object | No | Sparse fieldset selection per resource type |
| `filters` | object | No | Filter conditions per resource type |
| `sorts` | array | No | Sort criteria in priority order |
| `pagination` | object | No | Pagination parameters |
| `relationships` | array | No | Relationships to include |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `capabilities` | array | Query capabilities the function supports |

---

## Behavior

### Request Processing

When the query extension is included:

1. Server MUST validate all query parameters against function schema
2. Server MUST apply filters before sorting
3. Server MUST apply sorting before pagination
4. Server MUST include requested relationships in `included` array
5. Server MUST return only requested fields if sparse fieldsets specified

### Response Format

Query responses MUST use resource object format:

```json
{
  "result": {
    "data": { ... },      // Resource object(s)
    "included": [ ... ],  // Related resources (if requested)
    "meta": { ... }       // Query metadata (pagination info, etc.)
  }
}
```

### Capability Declaration

Functions SHOULD declare which query capabilities they support via `urn:cline:forrst:fn:describe`:

```json
{
  "result": {
    "function": "orders.list",
    "extensions": {
      "urn:forrst:ext:query": {
        "capabilities": ["filtering", "sorting", "pagination", "relationships"],
        "filters": {
          "self": ["id", "status", "created_at", "total_amount"],
          "customer": ["id", "type"]
        },
        "sorts": {
          "self": ["id", "status", "created_at", "total_amount"]
        },
        "relationships": {
          "available": ["customer", "items"],
          "nested": { "items": ["product"] },
          "max_depth": 3
        },
        "pagination": {
          "styles": ["cursor", "offset"],
          "default_limit": 25,
          "max_limit": 100
        }
      }
    }
  }
}
```

---

## Examples

### Basic Query Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "call": {
    "function": "orders.list",
    "version": "1.0.0",
    "arguments": {}
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:query",
      "options": {
        "filters": {
          "self": [
            { "attribute": "status", "operator": "in", "value": ["pending", "processing"] }
          ]
        },
        "sorts": [
          { "attribute": "created_at", "direction": "desc" }
        ],
        "pagination": {
          "limit": 25
        }
      }
    }
  ]
}
```

### Query Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": {
    "data": [
      {
        "type": "order",
        "id": "12345",
        "attributes": {
          "status": "pending",
          "total_amount": { "amount": "99.99", "currency": "USD" },
          "created_at": "2024-01-15T10:30:00Z"
        }
      },
      {
        "type": "order",
        "id": "12346",
        "attributes": {
          "status": "processing",
          "total_amount": { "amount": "149.99", "currency": "USD" },
          "created_at": "2024-01-15T09:15:00Z"
        }
      }
    ],
    "meta": {
      "pagination": {
        "limit": 25,
        "next_cursor": "eyJpZCI6MTIzNDZ9",
        "has_more": true
      }
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:query",
      "data": {
        "capabilities": ["filtering", "sorting", "pagination", "relationships"]
      }
    }
  ]
}
```

### With Relationships

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_124",
  "call": {
    "function": "orders.get",
    "version": "1.0.0",
    "arguments": { "id": "12345" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:query",
      "options": {
        "fields": {
          "self": ["id", "status", "total_amount"],
          "customer": ["id", "name", "email"]
        },
        "relationships": ["customer", "items"]
      }
    }
  ]
}
```

### Relationship Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_124",
  "result": {
    "data": {
      "type": "order",
      "id": "12345",
      "attributes": {
        "status": "pending",
        "total_amount": { "amount": "99.99", "currency": "USD" }
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
      },
      {
        "type": "order_item",
        "id": "1",
        "attributes": {
          "quantity": 2,
          "price": { "amount": "29.99", "currency": "USD" }
        }
      },
      {
        "type": "order_item",
        "id": "2",
        "attributes": {
          "quantity": 1,
          "price": { "amount": "39.99", "currency": "USD" }
        }
      }
    ]
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:query",
      "data": {
        "capabilities": ["filtering", "sorting", "pagination", "relationships", "sparse_fieldsets"]
      }
    }
  ]
}
```

### Complex Query

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_complex",
  "call": {
    "function": "tracking_events.list",
    "version": "1.0.0",
    "arguments": {}
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:query",
      "options": {
        "fields": {
          "self": ["id", "status", "location", "occurred_at"],
          "shipment": ["id", "tracking_number"]
        },
        "filters": {
          "self": [
            { "attribute": "occurred_at", "operator": "greater_than", "value": "2024-01-01T00:00:00Z" },
            { "attribute": "status", "operator": "in", "value": ["in_transit", "delivered"] }
          ],
          "shipment": [
            { "attribute": "carrier", "operator": "equals", "value": "posti" }
          ]
        },
        "sorts": [
          { "attribute": "occurred_at", "direction": "desc" }
        ],
        "relationships": ["shipment"],
        "pagination": {
          "limit": 50
        }
      }
    }
  ]
}
```

---

## Error Handling

### Invalid Filter Attribute

```json
{
  "errors": [{
    "code": "INVALID_ARGUMENTS",
    "message": "Filter attribute not allowed: secret_field",
    "source": {
      "pointer": "/extensions/0/options/filters/self/0/attribute"
    },
    "details": {
      "attribute": "secret_field",
      "allowed": ["id", "status", "created_at"]
    }
  }]
}
```

### Invalid Sort Attribute

```json
{
  "errors": [{
    "code": "INVALID_ARGUMENTS",
    "message": "Sort attribute not allowed: internal_score",
    "source": {
      "pointer": "/extensions/0/options/sorts/0/attribute"
    }
  }]
}
```

### Relationship Not Available

```json
{
  "errors": [{
    "code": "INVALID_ARGUMENTS",
    "message": "Relationship not available: secret_notes",
    "source": {
      "pointer": "/extensions/0/options/relationships/0"
    },
    "details": {
      "relationship": "secret_notes",
      "available": ["customer", "items"]
    }
  }]
}
```

### Pagination Limit Exceeded

```json
{
  "errors": [{
    "code": "INVALID_ARGUMENTS",
    "message": "Pagination limit exceeds maximum",
    "source": {
      "pointer": "/extensions/0/options/pagination/limit"
    },
    "details": {
      "requested": 500,
      "max_limit": 100
    }
  }]
}
```

### Field Not Allowed

```json
{
  "errors": [{
    "code": "INVALID_ARGUMENTS",
    "message": "Field not allowed: secret_notes",
    "source": {
      "pointer": "/extensions/0/options/fields/self/3"
    },
    "details": {
      "field": "secret_notes",
      "resource": "self",
      "allowed": ["id", "order_number", "status", "total_amount", "created_at", "updated_at"]
    }
  }]
}
```

---

## Migration from Arguments

If previously using query parameters in `call.arguments`, migrate to the query extension:

**Before (arguments):**
```json
{
  "call": {
    "function": "orders.list",
    "version": "1.0.0",
    "arguments": {
      "filters": { ... },
      "sorts": [ ... ],
      "pagination": { ... }
    }
  }
}
```

**After (extension):**
```json
{
  "call": {
    "function": "orders.list",
    "version": "1.0.0",
    "arguments": {}
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:query",
      "options": {
        "filters": { ... },
        "sorts": [ ... ],
        "pagination": { ... }
      }
    }
  ]
}
```

This separation clarifies that query capabilities are optional protocol features, not function-specific arguments.

---

## Discovery

Functions SHOULD advertise query capabilities via `urn:cline:forrst:fn:describe`:

```json
{
  "result": {
    "function": "orders.list",
    "extensions": {
      "urn:forrst:ext:query": {
        "capabilities": ["filtering", "sorting", "pagination", "relationships", "sparse_fieldsets"],
        "filters": {
          "self": ["id", "status", "created_at", "total_amount"],
          "customer": ["id", "type", "country_code"]
        },
        "sorts": {
          "self": ["id", "order_number", "status", "total_amount", "created_at", "updated_at"],
          "default": [{ "attribute": "created_at", "direction": "desc" }]
        },
        "fields": {
          "self": ["id", "order_number", "status", "total_amount", "item_count", "notes", "created_at", "updated_at"],
          "customer": ["id", "name", "email", "type", "country_code"],
          "items": ["id", "sku", "name", "quantity", "price", "total"],
          "defaults": {
            "self": ["id", "order_number", "status", "total_amount", "created_at"]
          }
        },
        "relationships": {
          "available": ["customer", "items", "shipping_address", "billing_address"],
          "nested": {
            "items": ["product", "product.category"]
          },
          "max_depth": 3
        },
        "pagination": {
          "styles": ["cursor", "offset"],
          "default_limit": 25,
          "max_limit": 100
        }
      }
    }
  }
}
```
