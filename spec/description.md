---
title: Description
description: Machine-readable API description for Forrst services
---

# Description

> Machine-readable API description for Forrst services

---

## Overview

Forrst Description provides a standardized interface description format for Forrst APIs. It enables both humans and machines to discover service capabilities without accessing source code or documentation.

Services supporting description MUST implement `urn:cline:forrst:fn:describe`.

---

## Specification Version

**Current Version:** 0.1

Version follows [Semantic Versioning](https://semver.org/). Tools supporting version 0.1.0 should work with all 0.1.x documents.

---

## Document Structure

### Description Document Object

The root object describing a Forrst service.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `forrst` | string | YES | Forrst protocol version (e.g., `"0.1.0"`) |
| `describe` | string | YES | Description spec version (e.g., `"0.1.0"`) |
| `info` | [Info Object](#info-object) | YES | Service metadata |
| `servers` | [[Server Object](#server-object)] | NO | Connectivity information |
| `functions` | [[Function Object](#function-object)] | YES | Available functions |
| `resources` | Map[string, [Resource Object](#resource-object)] | NO | Resource type definitions |
| `components` | [Components Object](#components-object) | NO | Reusable definitions |
| `external_docs` | [External Documentation Object](#external-documentation-object) | NO | Additional documentation |

#### Example

```json
{
  "forrst": "0.1.0",
  "describe": "0.1.0",
  "info": {
    "title": "Orders API",
    "version": "2.3.0",
    "description": "Order management service"
  },
  "functions": [
    {
      "name": "orders.get",
      "version": "2.0.0",
      "summary": "Retrieve an order by ID",
      "arguments": [...],
      "result": {...}
    }
  ]
}
```

---

## Info Object

Provides service metadata.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | YES | Service title |
| `version` | string | YES | Service version (not protocol version) |
| `description` | string | NO | Service description (Markdown supported) |
| `terms_of_service` | string | NO | URL to terms of service |
| `contact` | [Contact Object](#contact-object) | NO | Contact information |
| `license` | [License Object](#license-object) | NO | License information |

### Contact Object

| Field | Type | Required |
|-------|------|----------|
| `name` | string | NO |
| `url` | string | NO |
| `email` | string | NO |

### License Object

| Field | Type | Required |
|-------|------|----------|
| `name` | string | YES |
| `url` | string | NO |

#### Example

```json
{
  "info": {
    "title": "Orders API",
    "version": "2.3.0",
    "description": "Manages customer orders and fulfillment",
    "contact": {
      "name": "Platform Team",
      "email": "platform@example.com"
    },
    "license": {
      "name": "Proprietary"
    }
  }
}
```

---

## Server Object

Represents a server endpoint.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | YES | Server name |
| `url` | string | YES | Server URL (supports variables) |
| `description` | string | NO | Server description |
| `variables` | Map[string, [Server Variable](#server-variable-object)] | NO | URL template variables |

### Server Variable Object

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enum` | [string] | NO | Allowed values |
| `default` | string | YES | Default value |
| `description` | string | NO | Variable description |

#### Example

```json
{
  "servers": [
    {
      "name": "production",
      "url": "https://orders-api.internal",
      "description": "Production cluster"
    },
    {
      "name": "staging",
      "url": "https://orders-api.staging.internal",
      "description": "Staging environment"
    }
  ]
}
```

---

## Function Object

Describes a callable function. Each function name + version combination MUST be unique.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | YES | Function name (e.g., `orders.create`) |
| `version` | string | YES | Function version |
| `summary` | string | NO | Brief description |
| `description` | string | NO | Detailed description (Markdown supported) |
| `tags` | [[Tag Object](#tag-object) \| Reference] | NO | Logical grouping |
| `arguments` | [[Argument Object](#argument-object)] | YES | Function parameters |
| `result` | [Result Object](#result-object) | NO | Return value schema |
| `errors` | [[Error Definition](#error-definition-object)] | NO | Possible errors |
| `query` | [Query Capabilities Object](#query-capabilities-object) | NO | Query features for list functions |
| `deprecated` | [Deprecated Object](#deprecated-object) | NO | Deprecation info (presence indicates deprecated) |
| `side_effects` | [string] | NO | Side effects: `create`, `update`, `delete`. Empty array means read-only. |
| `discoverable` | boolean | NO | Whether included in description (default: `true`) |
| `examples` | [[Example Object](#example-object)] | NO | Usage examples |
| `external_docs` | [External Documentation Object](#external-documentation-object) | NO | Additional documentation |

#### Side Effects

The `side_effects` array indicates what state changes a function may cause:

| Value | Description |
|-------|-------------|
| `create` | Creates new resources |
| `update` | Modifies existing resources |
| `delete` | Removes resources |

An empty array (`[]`) or omitted field indicates a read-only function with no side effects.

Functions with multiple effects (e.g., sync operations) list all applicable values:

```json
{
  "name": "inventory.sync",
  "side_effects": ["create", "update", "delete"]
}
```

#### Example

```json
{
  "name": "orders.create",
  "version": "2.0.0",
  "summary": "Create a new order",
  "description": "Creates an order for a customer with the specified items.",
  "tags": [{ "name": "orders" }],
  "side_effects": ["create"],
  "arguments": [
    {
      "name": "customer_id",
      "schema": { "type": "string" },
      "required": true,
      "description": "Customer identifier"
    },
    {
      "name": "items",
      "schema": {
        "type": "array",
        "items": { "$ref": "#/components/schemas/OrderItem" }
      },
      "required": true,
      "description": "Order line items"
    }
  ],
  "result": {
    "resource": "order",
    "description": "The created order"
  },
  "errors": [
    { "$ref": "#/components/errors/CustomerNotFound" },
    { "$ref": "#/components/errors/InsufficientInventory" }
  ],
  "idempotent": false
}
```

#### Non-Discoverable Functions

Functions marked `discoverable: false` MUST NOT appear in `urn:cline:forrst:fn:describe` responses. This allows debug, admin, or infrastructure functions to exist without being advertised.

Non-discoverable functions remain callable—they simply aren't listed in description.

---

## Argument Object

Describes a function parameter.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | YES | Parameter name |
| `schema` | Schema Object | YES | JSON Schema for the parameter |
| `required` | boolean | NO | Whether required (default: `false`) |
| `summary` | string | NO | Brief description |
| `description` | string | NO | Detailed description |
| `default` | any | NO | Default value if not provided |
| `deprecated` | [Deprecated Object](#deprecated-object) | NO | Deprecation info (presence indicates deprecated) |
| `examples` | [any] | NO | Example values |

**Ordering Rule:** Required parameters SHOULD precede optional parameters.

#### Example

```json
{
  "name": "customer_id",
  "schema": {
    "type": "string",
    "pattern": "^cust_[a-zA-Z0-9]+$"
  },
  "required": true,
  "description": "Unique customer identifier",
  "examples": ["cust_abc123"]
}
```

---

## Result Object

Describes the function return value.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `resource` | string | NO | Resource type name (for resource responses) |
| `schema` | Schema Object | NO | JSON Schema (for non-resource responses) |
| `collection` | boolean | NO | Whether returns array of resources (default: `false`) |
| `description` | string | NO | Result description |

One of `resource` or `schema` SHOULD be provided.

#### Examples

**Single Resource:**

```json
{
  "result": {
    "resource": "order",
    "description": "The requested order"
  }
}
```

**Resource Collection:**

```json
{
  "result": {
    "resource": "order",
    "collection": true,
    "description": "List of matching orders"
  }
}
```

**Non-Resource:**

```json
{
  "result": {
    "schema": {
      "type": "object",
      "properties": {
        "valid": { "type": "boolean" },
        "errors": {
          "type": "array",
          "items": { "type": "string" }
        }
      }
    },
    "description": "Validation result"
  }
}
```

---

## Resource Object

Defines a resource type and its queryable capabilities.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | YES | Resource type identifier |
| `description` | string | NO | Resource description |
| `attributes` | Map[string, [Attribute Object](#attribute-object)] | YES | Resource attributes |
| `relationships` | Map[string, [Relationship Definition](#relationship-definition-object)] | NO | Available relationships |
| `meta` | [Schema Object] | NO | Schema for resource-level metadata |

#### Example

```json
{
  "resources": {
    "order": {
      "type": "order",
      "description": "A customer order",
      "attributes": {
        "id": {
          "schema": { "type": "string" },
          "description": "Unique identifier",
          "filterable": true,
          "sortable": false
        },
        "order_number": {
          "schema": { "type": "string" },
          "description": "Human-readable order number",
          "filterable": true,
          "sortable": true
        },
        "status": {
          "schema": {
            "type": "string",
            "enum": ["pending", "processing", "shipped", "delivered", "cancelled"]
          },
          "description": "Order status",
          "filterable": true,
          "filter_operators": ["equals", "not_equals", "in"],
          "sortable": true
        },
        "total_amount": {
          "schema": { "$ref": "#/components/schemas/Money" },
          "description": "Order total",
          "filterable": true,
          "filter_operators": ["equals", "greater_than", "less_than", "between"],
          "sortable": true
        },
        "created_at": {
          "schema": { "type": "string", "format": "date-time" },
          "description": "Creation timestamp",
          "filterable": true,
          "filter_operators": ["equals", "greater_than", "less_than", "between"],
          "sortable": true
        }
      },
      "relationships": {
        "customer": {
          "resource": "customer",
          "cardinality": "one",
          "description": "The ordering customer",
          "filterable": true
        },
        "items": {
          "resource": "order_item",
          "cardinality": "many",
          "description": "Order line items"
        }
      }
    }
  }
}
```

---

## Attribute Object

Defines a resource attribute.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `schema` | Schema Object | YES | JSON Schema for the attribute |
| `description` | string | NO | Attribute description |
| `filterable` | boolean | NO | Can be used in filters (default: `false`) |
| `filter_operators` | [string] | NO | Allowed filter operators |
| `sortable` | boolean | NO | Can be used in sorts (default: `false`) |
| `sparse` | boolean | NO | Can be sparse-selected (default: `true`) |
| `deprecated` | [Deprecated Object](#deprecated-object) | NO | Deprecation info (presence indicates deprecated) |

### Filter Operators

When `filterable` is `true`, specify allowed operators:

| Operator | Description |
|----------|-------------|
| `equals` | Exact match |
| `not_equals` | Not equal |
| `greater_than` | Greater than |
| `greater_than_or_equal_to` | Greater than or equal |
| `less_than` | Less than |
| `less_than_or_equal_to` | Less than or equal |
| `like` | Pattern match (SQL LIKE) |
| `not_like` | Negated pattern match |
| `in` | Value in array |
| `not_in` | Value not in array |
| `between` | Value between two bounds |
| `is_null` | Is null check |
| `is_not_null` | Is not null check |

If `filter_operators` is omitted, defaults to `["equals"]`.

---

## Relationship Definition Object

Defines a relationship to another resource.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `resource` | string | YES | Related resource type |
| `cardinality` | string | YES | `"one"` or `"many"` |
| `description` | string | NO | Relationship description |
| `filterable` | boolean | NO | Can filter by related attributes |
| `includable` | boolean | NO | Can be included (default: `true`) |
| `nested` | [string] | NO | Nested relationships allowed (e.g., `["product"]`) |

---

## Query Capabilities Object

Describes query features for list/search functions.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `filters` | [Filters Capability](#filters-capability) | NO | Filter support |
| `sorts` | [Sorts Capability](#sorts-capability) | NO | Sort support |
| `fields` | [Fields Capability](#fields-capability) | NO | Sparse fieldset support |
| `relationships` | [Relationships Capability](#relationships-capability) | NO | Relationship inclusion |
| `pagination` | [Pagination Capability](#pagination-capability) | NO | Pagination support |

### Filters Capability

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | YES | Whether filtering is supported |
| `boolean_logic` | boolean | NO | Supports `and`/`or` combinators |
| `resources` | [string] | NO | Filterable resource types (default: `["self"]`) |

### Sorts Capability

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | YES | Whether sorting is supported |
| `max_sorts` | integer | NO | Maximum simultaneous sort fields |
| `default_sort` | [Sort Default](#sort-default) | NO | Default sort if none specified |

#### Sort Default

```json
{
  "attribute": "created_at",
  "direction": "desc"
}
```

### Fields Capability

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | YES | Whether sparse fieldsets supported |
| `default_fields` | Map[string, [string]] | NO | Default fields per resource type |

### Relationships Capability

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | YES | Whether relationship inclusion supported |
| `available` | [string] | NO | Available relationships |
| `max_depth` | integer | NO | Maximum nesting depth |

### Pagination Capability

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `styles` | [string] | YES | Supported styles: `"offset"`, `"cursor"`, `"keyset"` |
| `default_style` | string | NO | Default pagination style |
| `default_limit` | integer | NO | Default page size |
| `max_limit` | integer | NO | Maximum page size |

#### Example

```json
{
  "query": {
    "filters": {
      "enabled": true,
      "boolean_logic": true,
      "resources": ["self", "customer"]
    },
    "sorts": {
      "enabled": true,
      "max_sorts": 3,
      "default_sort": {
        "attribute": "created_at",
        "direction": "desc"
      }
    },
    "fields": {
      "enabled": true,
      "default_fields": {
        "self": ["id", "order_number", "status", "total_amount", "created_at"]
      }
    },
    "relationships": {
      "enabled": true,
      "available": ["customer", "items", "shipping_address"],
      "max_depth": 2
    },
    "pagination": {
      "styles": ["cursor", "offset"],
      "default_style": "cursor",
      "default_limit": 25,
      "max_limit": 100
    }
  }
}
```

---

## Error Definition Object

Defines a possible error response.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `code` | string | YES | Error code |
| `message` | string | YES | Human-readable message |
| `description` | string | NO | When this error occurs |
| `details` | Schema Object | NO | Schema for error details field |

#### Example

```json
{
  "code": "CUSTOMER_NOT_FOUND",
  "message": "Customer not found",
  "description": "Returned when the specified customer_id does not exist",
}
```

---

## Example Object

Provides a complete request/response example.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | YES | Example name |
| `summary` | string | NO | Brief description |
| `description` | string | NO | Detailed explanation |
| `arguments` | object | YES | Example parameter values |
| `result` | any | NO | Expected result (omit for error examples) |
| `error` | object | NO | Expected error (for error examples) |

#### Example

```json
{
  "examples": [
    {
      "name": "Create simple order",
      "summary": "Creates an order with a single item",
      "arguments": {
        "customer_id": "cust_abc123",
        "items": [
          { "sku": "WIDGET-01", "quantity": 2 }
        ]
      },
      "result": {
        "data": {
          "type": "order",
          "id": "ord_xyz789",
          "attributes": {
            "order_number": "ORD-2024-0001",
            "status": "pending",
            "total_amount": { "amount": "59.98", "currency": "USD" }
          }
        }
      }
    },
    {
      "name": "Invalid customer",
      "summary": "Error when customer doesn't exist",
      "arguments": {
        "customer_id": "cust_invalid",
        "items": [
          { "sku": "WIDGET-01", "quantity": 1 }
        ]
      },
      "errors": [{
        "code": "CUSTOMER_NOT_FOUND",
        "message": "Customer not found",
        "source": {
          "pointer": "/call/arguments/customer_id"
        }
      }]
    }
  ]
}
```

---

## Tag Object

Logical grouping for functions.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | YES | Tag name |
| `summary` | string | NO | Brief description |
| `description` | string | NO | Detailed description |
| `external_docs` | [External Documentation Object](#external-documentation-object) | NO | Additional documentation |

---

## Deprecated Object

Indicates a function is deprecated. Presence of this object means the function is deprecated.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `reason` | string | NO | Why deprecated and what to use instead |
| `sunset` | string | NO | ISO 8601 date when function will be removed |

#### Example

```json
{
  "deprecated": {
    "reason": "Use version 2.0.0 which supports bulk operations",
    "sunset": "2025-06-01"
  }
}
```

---

## External Documentation Object

Reference to external documentation.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `url` | string | YES | Documentation URL |
| `description` | string | NO | Description |

---

## Components Object

Holds reusable definitions.

| Field | Type | Description |
|-------|------|-------------|
| `schemas` | Map[string, Schema Object] | Reusable JSON Schemas |
| `arguments` | Map[string, [Argument Object](#argument-object)] | Reusable parameters |
| `errors` | Map[string, [Error Definition](#error-definition-object)] | Reusable error definitions |
| `examples` | Map[string, [Example Object](#example-object)] | Reusable examples |
| `tags` | Map[string, [Tag Object](#tag-object)] | Reusable tags |
| `resources` | Map[string, [Resource Object](#resource-object)] | Reusable resource definitions |

Component keys MUST match pattern: `^[a-zA-Z0-9._-]+$`

Objects in components have no effect unless referenced.

#### Example

```json
{
  "components": {
    "schemas": {
      "Money": {
        "type": "object",
        "properties": {
          "amount": { "type": "string", "pattern": "^-?\\d+\\.\\d{2}$" },
          "currency": { "type": "string", "pattern": "^[A-Z]{3}$" }
        },
        "required": ["amount", "currency"]
      },
      "OrderItem": {
        "type": "object",
        "properties": {
          "sku": { "type": "string" },
          "quantity": { "type": "integer", "minimum": 1 }
        },
        "required": ["sku", "quantity"]
      }
    },
    "errors": {
      "CUSTOMER_NOT_FOUND": {
        "code": "CUSTOMER_NOT_FOUND",
        "message": "Customer not found",
      },
      "INSUFFICIENT_INVENTORY": {
        "code": "INSUFFICIENT_INVENTORY",
        "message": "Insufficient inventory",
        "details": {
          "type": "object",
          "properties": {
            "sku": { "type": "string" },
            "requested": { "type": "integer" },
            "available": { "type": "integer" }
          }
        }
      }
    }
  }
}
```

---

## Reference Object

Enables reuse via JSON Reference.

| Field | Type | Required |
|-------|------|----------|
| `$ref` | string | YES |

Reference paths follow JSON Pointer syntax:

- `#/components/schemas/Money` — Local reference
- `common.json#/components/schemas/Money` — External reference

#### Example

```json
{
  "arguments": [
    {
      "name": "amount",
      "schema": { "$ref": "#/components/schemas/Money" },
      "required": true
    }
  ]
}
```

---

## Schema Object

Follows [JSON Schema Draft-07](https://json-schema.org/specification-links.html#draft-7).

Common patterns:

```json
// String with pattern
{
  "type": "string",
  "pattern": "^[a-z0-9_]+$",
  "minLength": 1,
  "maxLength": 64
}

// Enum
{
  "type": "string",
  "enum": ["pending", "processing", "shipped"]
}

// Array
{
  "type": "array",
  "items": { "$ref": "#/components/schemas/OrderItem" },
  "minItems": 1
}

// Object
{
  "type": "object",
  "properties": {
    "name": { "type": "string" },
    "email": { "type": "string", "format": "email" }
  },
  "required": ["name", "email"],
  "additionalProperties": false
}
```

---

## Description Function

Services MUST implement `urn:cline:forrst:fn:describe` to return the Description Document.

### urn:cline:forrst:fn:describe

Returns the complete description document or describes a specific function.

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `function` | string | NO | Specific function to describe |
| `version` | string | NO | Specific version (with `function`) |

**Result:** Description Document Object (or subset for specific function)

Functions with `discoverable: false` are always excluded from the response.

#### Full Description

```json
// Request
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_describe",
  "call": {
    "function": "urn:cline:forrst:fn:describe",
    "version": "1.0.0",
    "arguments": {}
  }
}

// Response
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_describe",
  "result": {
    "forrst": "0.1.0",
    "describe": "0.1.0",
    "info": {
      "title": "Orders API",
      "version": "2.3.0"
    },
    "functions": [
      { "name": "orders.get", "version": "2.0.0", ... },
      { "name": "orders.list", "version": "2.0.0", ... },
      { "name": "orders.create", "version": "2.0.0", ... }
    ],
    "resources": {
      "order": { ... },
      "order_item": { ... }
    },
    "components": { ... }
  }
}
```

#### Single Function

```json
// Request
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_describe_fn",
  "call": {
    "function": "urn:cline:forrst:fn:describe",
    "version": "1.0.0",
    "arguments": {
      "function": "orders.list",
      "version": "2.0.0"
    }
  }
}

// Response
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_describe_fn",
  "result": {
    "name": "orders.list",
    "version": "2.0.0",
    "summary": "List orders",
    "arguments": [...],
    "result": {
      "resource": "order",
      "collection": true
    },
    "query": {
      "filters": { "enabled": true, ... },
      "sorts": { "enabled": true, ... },
      "pagination": { "styles": ["cursor"], ... }
    }
  }
}
```

---

## Specification Extensions

Custom extensions use fields prefixed with `x-`:

```json
{
  "name": "orders.create",
  "version": "2.0.0",
  "x-rate-limit": {
    "requests": 100,
    "window": { "value": 1, "unit": "minute" }
  },
  "x-owner": "orders-team"
}
```

Extensions MAY appear in any object. Tooling MAY ignore unknown extensions.

---

## Complete Example

```json
{
  "forrst": "0.1.0",
  "describe": "0.1.0",
  "info": {
    "title": "Orders API",
    "version": "2.3.0",
    "description": "Order management service for the e-commerce platform",
    "contact": {
      "name": "Platform Team",
      "email": "platform@example.com"
    }
  },
  "servers": [
    {
      "name": "production",
      "url": "https://orders-api.internal"
    }
  ],
  "functions": [
    {
      "name": "orders.get",
      "version": "2.0.0",
      "summary": "Get an order by ID",
      "tags": [{ "name": "orders" }],
      "arguments": [
        {
          "name": "id",
          "schema": { "type": "string" },
          "required": true,
          "description": "Order ID"
        }
      ],
      "result": {
        "resource": "order",
        "description": "The requested order"
      },
      "query": {
        "fields": {
          "enabled": true
        },
        "relationships": {
          "enabled": true,
          "available": ["customer", "items", "shipping_address"],
          "max_depth": 2
        }
      },
      "errors": [
        { "$ref": "#/components/errors/NotFound" }
      ],
      "examples": [
        {
          "name": "Get order",
          "arguments": { "id": "ord_xyz789" },
          "result": {
            "details": {
              "type": "order",
              "id": "ord_xyz789",
              "attributes": {
                "order_number": "ORD-2024-0001",
                "status": "pending",
                "total_amount": { "amount": "99.99", "currency": "USD" },
                "created_at": "2024-01-15T10:30:00Z"
              }
            }
          }
        }
      ]
    },
    {
      "name": "orders.list",
      "version": "2.0.0",
      "summary": "List orders",
      "tags": [{ "name": "orders" }],
      "arguments": [],
      "result": {
        "resource": "order",
        "collection": true,
        "description": "Paginated list of orders"
      },
      "query": {
        "filters": {
          "enabled": true,
          "boolean_logic": true,
          "resources": ["self", "customer"]
        },
        "sorts": {
          "enabled": true,
          "max_sorts": 2,
          "default_sort": { "attribute": "created_at", "direction": "desc" }
        },
        "fields": {
          "enabled": true,
          "default_fields": {
            "self": ["id", "order_number", "status", "total_amount", "created_at"]
          }
        },
        "relationships": {
          "enabled": true,
          "available": ["customer", "items"],
          "max_depth": 2
        },
        "pagination": {
          "styles": ["cursor", "offset"],
          "default_style": "cursor",
          "default_limit": 25,
          "max_limit": 100
        }
      }
    },
    {
      "name": "orders.create",
      "version": "2.0.0",
      "summary": "Create a new order",
      "tags": [{ "name": "orders" }],
      "arguments": [
        {
          "name": "customer_id",
          "schema": { "type": "string" },
          "required": true
        },
        {
          "name": "items",
          "schema": {
            "type": "array",
            "items": { "$ref": "#/components/schemas/OrderItemInput" },
            "minItems": 1
          },
          "required": true
        },
        {
          "name": "shipping_address_id",
          "schema": { "type": "string" },
          "required": false
        }
      ],
      "result": {
        "resource": "order",
        "description": "The created order"
      },
      
      "errors": [
        { "$ref": "#/components/errors/NotFound" },
        { "$ref": "#/components/errors/InvalidArguments" },
        { "$ref": "#/components/errors/InsufficientInventory" }
      ]
    }
  ],
  "resources": {
    "order": {
      "type": "order",
      "description": "A customer order",
      "attributes": {
        "id": {
          "schema": { "type": "string" },
          "filterable": true,
          "sortable": false,
          "sparse": true
        },
        "order_number": {
          "schema": { "type": "string" },
          "filterable": true,
          "filter_operators": ["equals", "like"],
          "sortable": true
        },
        "status": {
          "schema": {
            "type": "string",
            "enum": ["pending", "processing", "shipped", "delivered", "cancelled"]
          },
          "filterable": true,
          "filter_operators": ["equals", "not_equals", "in"],
          "sortable": true
        },
        "total_amount": {
          "schema": { "$ref": "#/components/schemas/Money" },
          "filterable": true,
          "filter_operators": ["equals", "greater_than", "less_than", "between"],
          "sortable": true
        },
        "item_count": {
          "schema": { "type": "integer" },
          "filterable": false,
          "sortable": true
        },
        "created_at": {
          "schema": { "type": "string", "format": "date-time" },
          "filterable": true,
          "filter_operators": ["equals", "greater_than", "less_than", "between"],
          "sortable": true
        },
        "updated_at": {
          "schema": { "type": "string", "format": "date-time" },
          "filterable": true,
          "filter_operators": ["greater_than", "less_than"],
          "sortable": true
        }
      },
      "relationships": {
        "customer": {
          "resource": "customer",
          "cardinality": "one",
          "filterable": true,
          "includable": true
        },
        "items": {
          "resource": "order_item",
          "cardinality": "many",
          "filterable": false,
          "includable": true,
          "nested": ["product"]
        },
        "shipping_address": {
          "resource": "address",
          "cardinality": "one",
          "filterable": false,
          "includable": true
        }
      }
    },
    "customer": {
      "type": "customer",
      "attributes": {
        "id": { "schema": { "type": "string" }, "filterable": true },
        "name": { "schema": { "type": "string" }, "filterable": true, "filter_operators": ["equals", "like"] },
        "email": { "schema": { "type": "string", "format": "email" }, "filterable": true },
        "type": {
          "schema": { "type": "string", "enum": ["standard", "premium", "vip"] },
          "filterable": true,
          "filter_operators": ["equals", "in"]
        }
      }
    }
  },
  "components": {
    "schemas": {
      "Money": {
        "type": "object",
        "properties": {
          "amount": { "type": "string", "pattern": "^-?\\d+\\.\\d{2}$" },
          "currency": { "type": "string", "pattern": "^[A-Z]{3}$" }
        },
        "required": ["amount", "currency"]
      },
      "OrderItemInput": {
        "type": "object",
        "properties": {
          "sku": { "type": "string" },
          "quantity": { "type": "integer", "minimum": 1 }
        },
        "required": ["sku", "quantity"]
      }
    },
    "errors": {
      "NOT_FOUND": {
        "code": "NOT_FOUND",
        "message": "Resource not found",
      },
      "INVALID_ARGUMENTS": {
        "code": "INVALID_ARGUMENTS",
        "message": "Invalid arguments provided",
      },
      "INSUFFICIENT_INVENTORY": {
        "code": "INSUFFICIENT_INVENTORY",
        "message": "Insufficient inventory",
        "details": {
          "type": "object",
          "properties": {
            "sku": { "type": "string" },
            "requested": { "type": "integer" },
            "available": { "type": "integer" }
          }
        }
      }
    }
  }
}
```

---

## Formatting Standards

| Aspect | Standard |
|--------|----------|
| Format | JSON (RFC 7159) |
| File name | `forrst.json` or `forrst-describe.json` |
| Case sensitivity | All field names are case-sensitive |
| Naming | `snake_case` for field names |
| Rich text | GitHub Flavored Markdown |
| Versioning | Semantic Versioning |
