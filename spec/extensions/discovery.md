
---
title: Discovery
description: Service introspection and capability discovery

---

# Discovery

> Service introspection and capability discovery

**Extension URN:** `urn:forrst:ext:discovery`


---

## Overview

The discovery extension provides service introspection functions that enable clients to discover what a server supports. This includes lightweight capability checks, full schema introspection, and reusable schema definitions.

This extension provides two functions:

| Function | Description |
|----------|-------------|
| `urn:cline:forrst:ext:discovery:fn:capabilities` | Lightweight capability summary |
| `urn:cline:forrst:ext:discovery:fn:describe` | Full service schema introspection |


---

## When to Use

Discovery functions SHOULD be used for:
- Initial client-server handshake
- Protocol version compatibility checking
- Dynamic client generation
- API exploration and documentation
- Feature detection before making calls


---

## Functions

### urn:cline:forrst:ext:discovery:fn:capabilities

Returns a lightweight summary of service capabilities without full schema details.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_caps",
  "call": {
    "function": "urn:cline:forrst:ext:discovery:fn:capabilities",
    "version": "1.0.0"
  }
}
```

**Response:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_caps",
  "result": {
    "service": "my-api",
    "protocolVersions": ["0.1.0"],
    "functions": [
      "users.list",
      "users.get",
      "orders.create"
    ],
    "extensions": [
      { "urn": "urn:forrst:ext:async", "version": "1.0.0" },
      { "urn": "urn:forrst:ext:caching", "version": "1.2.0" }
    ],
    "limits": {
      "maxRequestSize": 1048576,
      "rateLimit": 1000
    }
  }
}
```

**Result Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `service` | string | Yes | Service identifier |
| `protocolVersions` | array | Yes | Supported Forrst protocol versions |
| `functions` | array | Yes | Available function names |
| `extensions` | array | No | Enabled extensions with URNs and versions |
| `limits` | object | No | Service limits (rate limits, max sizes) |

**Extension Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `urn` | string | Yes | Extension URN |
| `version` | string | Yes | Supported extension version |


---

### urn:cline:forrst:ext:discovery:fn:describe

Returns the complete Forrst Discovery document with full schema introspection.

**Request (Full Service):**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_describe",
  "call": {
    "function": "urn:cline:forrst:ext:discovery:fn:describe",
    "version": "1.0.0"
  }
}
```

**Request (Single Function):**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_describe_fn",
  "call": {
    "function": "urn:cline:forrst:ext:discovery:fn:describe",
    "version": "1.0.0",
    "arguments": {
      "function": "users.list",
      "version": "1.0.0"
    }
  }
}
```

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `function` | string | No | Specific function to describe |
| `version` | string | No | Specific version (with function) |

**Response (Full Service):**

```json
{
  "forrst": "0.1.0",
  "discovery": "0.1",
  "info": {
    "title": "Event Management API",
    "version": "1.0.0",
    "description": "API for managing events, venues, and registrations",
    "termsOfService": "https://example.com/terms",
    "contact": {
      "name": "API Support",
      "url": "https://example.com/support",
      "email": "api@example.com"
    },
    "license": {
      "name": "Proprietary",
      "url": "https://example.com/license"
    }
  },
  "servers": [
    {
      "name": "production",
      "url": "https://{region}.api.example.com/forrst",
      "summary": "Production API server",
      "description": "Main production environment with full SLA guarantees",
      "variables": {
        "region": {
          "default": "us-east",
          "enum": ["us-east", "us-west", "eu-west", "ap-south"],
          "description": "Geographic region for the API endpoint"
        }
      },
      "extensions": [
        { "urn": "urn:forrst:ext:async", "version": "1.0.0" },
        { "urn": "urn:forrst:ext:caching", "version": "1.2.0" },
        { "urn": "urn:forrst:ext:query", "version": "1.0.0" }
      ]
    }
  ],
  "functions": [
    {
      "name": "events.list",
      "version": "1.0.0",
      "stability": "stable",
      "summary": "List all events",
      "description": "Returns a paginated list of events. Supports filtering by status and date range. Results are ordered by start date ascending.",
      "tags": [
        { "$ref": "#/components/tags/Events" }
      ],
      "arguments": [
        { "$ref": "#/components/contentDescriptors/StatusFilter" },
        { "$ref": "#/components/contentDescriptors/Pagination" }
      ],
      "result": {
        "name": "events",
        "summary": "List of events",
        "schema": {
          "type": "array",
          "items": { "$ref": "#/components/schemas/EventResource" }
        }
      },
      "errors": [
        { "$ref": "#/components/errors/Unauthorized" }
      ],
      "query": {
        "filters": {
          "allowed": ["status", "starts_at", "venue_id"],
          "operators": ["eq", "neq", "gt", "lt", "in"]
        },
        "sorts": {
          "allowed": ["starts_at", "name", "created_at"],
          "default": { "field": "starts_at", "direction": "asc" }
        },
        "pagination": {
          "strategies": ["offset", "cursor"],
          "defaultSize": 25,
          "maxSize": 100
        }
      },
      "examples": [
        { "$ref": "#/components/examplePairings/ListPublishedEvents" }
      ],
      "extensions": [
        { "urn": "urn:forrst:ext:caching", "ttl": 300 }
      ]
    },
    {
      "name": "events.get",
      "version": "1.0.0",
      "stability": "stable",
      "summary": "Get a single event by ID",
      "description": "Retrieves detailed information about a specific event including its venue.",
      "tags": [
        { "$ref": "#/components/tags/Events" }
      ],
      "arguments": [
        { "$ref": "#/components/contentDescriptors/EventId" }
      ],
      "result": {
        "name": "event",
        "summary": "The requested event",
        "schema": { "$ref": "#/components/schemas/EventResource" }
      },
      "errors": [
        { "$ref": "#/components/errors/NotFound" },
        { "$ref": "#/components/errors/Unauthorized" }
      ],
      "links": [
        { "$ref": "#/components/links/GetEventVenue" }
      ],
      "examples": [
        { "$ref": "#/components/examplePairings/GetSingleEvent" }
      ],
      "extensions": [
        { "urn": "urn:forrst:ext:caching", "ttl": 60 }
      ]
    },
    {
      "name": "events.create",
      "version": "1.0.0",
      "stability": "stable",
      "summary": "Create a new event",
      "sideEffects": [
        "sends_email",
        "creates_audit_log"
      ],
      "simulations": [
        {
          "name": "success",
          "description": "Event created successfully",
          "input": { "name": "Demo Event", "starts_at": "2024-12-01T10:00:00Z" },
          "output": { "id": "evt_demo_001", "name": "Demo Event", "status": "draft" }
        }
      ]
    },
    {
      "name": "events.legacy_create",
      "version": "1.0.0",
      "stability": "deprecated",
      "summary": "Create event (legacy)",
      "deprecated": {
        "reason": "Use events.create instead",
        "sunset": "2025-06-30"
      },
      "discoverable": false
    }
  ],
  "components": {
    "contentDescriptors": {
      "EventId": {
        "name": "id",
        "summary": "Event identifier",
        "required": true,
        "schema": { "type": "string", "format": "uuid" }
      },
      "VenueId": {
        "name": "venue_id",
        "summary": "Venue identifier",
        "required": true,
        "schema": { "type": "string", "format": "uuid" }
      },
      "StatusFilter": {
        "name": "status",
        "summary": "Filter by event status",
        "required": false,
        "schema": { "type": "string", "enum": ["draft", "published", "cancelled"] }
      },
      "Pagination": {
        "name": "pagination",
        "summary": "Pagination parameters",
        "required": false,
        "schema": { "$ref": "#/components/schemas/PaginationParams" }
      }
    },
    "schemas": {
      "EventResource": {
        "type": "object",
        "required": ["id", "name", "starts_at"],
        "properties": {
          "id": { "type": "string", "format": "uuid" },
          "name": { "type": "string", "maxLength": 255 },
          "description": { "type": "string" },
          "status": { "type": "string", "enum": ["draft", "published", "cancelled"] },
          "starts_at": { "type": "string", "format": "date-time" },
          "ends_at": { "type": "string", "format": "date-time" },
          "venue": { "$ref": "#/components/schemas/VenueResource" },
          "created_at": { "type": "string", "format": "date-time" },
          "updated_at": { "type": "string", "format": "date-time" }
        }
      },
      "VenueResource": {
        "type": "object",
        "required": ["id", "name"],
        "properties": {
          "id": { "type": "string", "format": "uuid" },
          "name": { "type": "string" },
          "address": { "type": "string" },
          "capacity": { "type": "integer", "minimum": 0 }
        }
      },
      "PaginationParams": {
        "type": "object",
        "properties": {
          "page": { "type": "integer", "minimum": 1, "default": 1 },
          "per_page": { "type": "integer", "minimum": 1, "maximum": 100, "default": 25 }
        }
      }
    },
    "errors": {
      "NotFound": {
        "code": "NOT_FOUND",
        "message": "The requested resource was not found"
      },
      "Unauthorized": {
        "code": "UNAUTHORIZED",
        "message": "Authentication required"
      },
      "ValidationError": {
        "code": "VALIDATION_ERROR",
        "message": "Request validation failed"
      }
    },
    "examples": {
      "PublishedEvent": {
        "name": "Published Conference",
        "summary": "A typical published event",
        "value": {
          "id": "evt_01H8X3Y4Z5A6B7C8D9E0F1G2H3",
          "name": "Tech Conference 2024",
          "status": "published",
          "starts_at": "2024-09-15T09:00:00Z",
          "ends_at": "2024-09-15T18:00:00Z"
        }
      },
      "DraftEvent": {
        "name": "Draft Event",
        "summary": "An event still in draft status",
        "value": {
          "id": "evt_02I9Y4Z5A6B7C8D9E0F1G2H3I4",
          "name": "Planning Meeting",
          "status": "draft",
          "starts_at": "2024-10-01T14:00:00Z"
        }
      }
    },
    "examplePairings": {
      "ListPublishedEvents": {
        "name": "List published events",
        "summary": "Retrieve all published events",
        "params": [
          {
            "name": "status filter",
            "value": "published"
          }
        ],
        "result": {
          "name": "Published events list",
          "value": [
            {
              "id": "evt_01H8X3Y4Z5A6B7C8D9E0F1G2H3",
              "name": "Tech Conference 2024",
              "status": "published",
              "starts_at": "2024-09-15T09:00:00Z"
            }
          ]
        }
      },
      "GetSingleEvent": {
        "name": "Get event by ID",
        "summary": "Retrieve a specific event",
        "params": [
          {
            "name": "event id",
            "value": "evt_01H8X3Y4Z5A6B7C8D9E0F1G2H3"
          }
        ],
        "result": {
          "name": "Event details",
          "value": {
            "id": "evt_01H8X3Y4Z5A6B7C8D9E0F1G2H3",
            "name": "Tech Conference 2024",
            "status": "published",
            "starts_at": "2024-09-15T09:00:00Z",
            "ends_at": "2024-09-15T18:00:00Z",
            "venue": {
              "id": "ven_01A2B3C4D5E6F7G8H9I0J1K2L3",
              "name": "Convention Center",
              "capacity": 5000
            }
          }
        }
      }
    },
    "links": {
      "GetEventVenue": {
        "name": "Get venue details",
        "summary": "Retrieve the venue for this event",
        "function": "venues.get",
        "params": {
          "venue_id": "$result.venue.id"
        }
      },
      "ListEventAttendees": {
        "name": "List attendees",
        "summary": "Retrieve attendees for this event",
        "function": "attendees.list",
        "params": {
          "event_id": "$result.id"
        }
      }
    },
    "tags": {
      "Events": {
        "name": "Events",
        "summary": "Event management functions",
        "description": "Functions for creating, reading, updating, and deleting events."
      },
      "Venues": {
        "name": "Venues",
        "summary": "Venue management functions",
        "description": "Functions for managing event venues and locations."
      },
      "Attendees": {
        "name": "Attendees",
        "summary": "Attendee management functions",
        "description": "Functions for managing event registrations and attendees."
      }
    }
  }
}
```

Note: The describe function returns an **unwrapped response** (no protocol envelope) as per the Forrst Discovery specification.

---

## Info Object

The Info Object provides metadata about the API.

**Info Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Human-readable API name |
| `version` | string | Yes | API version (semantic versioning recommended) |
| `description` | string | No | Detailed API description (supports Markdown) |
| `termsOfService` | string | No | URL to terms of service |
| `contact` | Contact Object | No | Contact information |
| `license` | License Object | No | License information |

### Contact Object

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | No | Contact name or team |
| `url` | string | No | URL to contact page or documentation |
| `email` | string | No | Contact email address |

### License Object

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | License name (e.g., "MIT", "Proprietary") |
| `url` | string | No | URL to full license text |

---

## Server Object

The Server Object describes an API endpoint where requests can be sent.

**Server Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Server identifier (e.g., "production", "staging") |
| `url` | string | Yes | Base URL for API requests (supports URL templating) |
| `summary` | string | No | Brief server description |
| `description` | string | No | Detailed explanation (supports Markdown) |
| `variables` | Map[string, Server Variable] | No | URL template variables |
| `extensions` | array | No | Supported extensions with versions |

### Server Variable Object

Used for URL templating when the server URL contains variables like `{region}`.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `default` | string | Yes | Default value for the variable |
| `enum` | [string] | No | Allowed values for the variable |
| `description` | string | No | Explanation of the variable |

**URL Templating Example:**

```json
{
  "servers": [{
    "name": "production",
    "url": "https://{region}.api.example.com/{version}/forrst",
    "variables": {
      "region": {
        "default": "us-east",
        "enum": ["us-east", "us-west", "eu-west", "ap-south"],
        "description": "Geographic region"
      },
      "version": {
        "default": "v1",
        "description": "API version prefix"
      }
    }
  }]
}
```

Clients resolve the URL by substituting variables with their values (or defaults).

---

## Components

The Components Object holds reusable definitions that can be referenced throughout the discovery document. All component types support the Reference Object pattern using `$ref`.

### components.schemas

JSON Schema definitions for data types. Follows JSON Schema Draft 7 specification.

```json
{
  "components": {
    "schemas": {
      "EventResource": {
        "type": "object",
        "required": ["id", "name"],
        "properties": {
          "id": { "type": "string", "format": "uuid" },
          "name": { "type": "string" },
          "venue": { "$ref": "#/components/schemas/VenueResource" }
        }
      }
    }
  }
}
```

**Schema Object Fields:** Any valid JSON Schema Draft 7 fields.

---

### components.contentDescriptors

Reusable descriptions for function arguments and results. Avoids duplication when the same parameter appears across multiple functions.

```json
{
  "components": {
    "contentDescriptors": {
      "EventId": {
        "name": "id",
        "summary": "Event identifier",
        "description": "The unique identifier for an event",
        "required": true,
        "schema": { "type": "string", "format": "uuid" }
      },
      "Pagination": {
        "name": "pagination",
        "summary": "Pagination parameters",
        "required": false,
        "schema": { "$ref": "#/components/schemas/PaginationParams" }
      }
    }
  }
}
```

**Content Descriptor Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Parameter name as used in function calls |
| `schema` | Schema Object | Yes | JSON Schema type definition |
| `summary` | string | No | Brief description |
| `description` | string | No | Detailed description (supports Markdown) |
| `required` | boolean | No | Whether the parameter is required (default: false) |
| `deprecated` | boolean | No | Marks parameter as deprecated |

**Usage in functions:**

```json
{
  "functions": [{
    "name": "events.get",
    "arguments": [
      { "$ref": "#/components/contentDescriptors/EventId" }
    ]
  }]
}
```

---

### components.errors

Reusable error definitions that can be referenced from function error arrays.

```json
{
  "components": {
    "errors": {
      "NotFound": {
        "code": "NOT_FOUND",
        "message": "The requested resource was not found",
        "data": {
          "type": "object",
          "properties": {
            "resource_type": { "type": "string" },
            "resource_id": { "type": "string" }
          }
        }
      }
    }
  }
}
```

**Error Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `code` | string | Yes | Machine-readable error code |
| `message` | string | Yes | Human-readable error message |
| `data` | Schema Object | No | Schema for additional error context |

---

### components.examples

Reusable example values that match the schema of a Content Descriptor or Schema Object.

```json
{
  "components": {
    "examples": {
      "PublishedEvent": {
        "name": "Published Conference",
        "summary": "A typical published event",
        "description": "Example of a fully configured event ready for attendees",
        "value": {
          "id": "evt_01H8X3Y4Z5A6B7C8D9E0F1G2H3",
          "name": "Tech Conference 2024",
          "status": "published",
          "starts_at": "2024-09-15T09:00:00Z"
        }
      }
    }
  }
}
```

**Example Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | No | Canonical identifier |
| `summary` | string | No | Brief description |
| `description` | string | No | Detailed explanation (supports Markdown) |
| `value` | any | No | Embedded literal example |
| `externalValue` | string | No | URL to external example (mutually exclusive with value) |

---

### components.examplePairings

Request-response pairs demonstrating complete function invocations. Links argument examples to expected results.

```json
{
  "components": {
    "examplePairings": {
      "GetSingleEvent": {
        "name": "Get event by ID",
        "summary": "Retrieve a specific event",
        "description": "Demonstrates fetching an event with all related data",
        "params": [
          { "name": "event id", "value": "evt_01H8X3Y4Z5A6B7C8D9E0F1G2H3" }
        ],
        "result": {
          "name": "Event details",
          "value": {
            "id": "evt_01H8X3Y4Z5A6B7C8D9E0F1G2H3",
            "name": "Tech Conference 2024",
            "status": "published"
          }
        }
      }
    }
  }
}
```

**Example Pairing Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Identifier for the pairing |
| `summary` | string | No | Brief description |
| `description` | string | No | Detailed explanation (supports Markdown) |
| `params` | [Example Object] | Yes | Array of parameter examples |
| `result` | Example Object | No | Expected response (omit for notification-style calls) |

**Usage in functions:**

```json
{
  "functions": [{
    "name": "events.get",
    "examples": [
      { "$ref": "#/components/examplePairings/GetSingleEvent" }
    ]
  }]
}
```

---

### components.links

Design-time links that describe relationships between functions. Enables clients to discover related operations.

```json
{
  "components": {
    "links": {
      "GetEventVenue": {
        "name": "Get venue details",
        "summary": "Retrieve the venue for this event",
        "function": "venues.get",
        "params": {
          "venue_id": "$result.venue.id"
        }
      }
    }
  }
}
```

**Link Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Canonical link identifier |
| `summary` | string | No | Brief description |
| `description` | string | No | Detailed explanation (supports Markdown) |
| `function` | string | No | Target function name |
| `params` | Map[string, any] | No | Parameters for target function |
| `server` | Server Object | No | Alternative server for the linked function |

**Runtime Expressions:**

The `params` map supports runtime expressions that reference values from the current result:

| Expression | Description |
|------------|-------------|
| `$result` | The entire result object |
| `$result.field` | A specific field from the result |
| `$result.nested.field` | Nested field access |

**Usage in functions:**

```json
{
  "functions": [{
    "name": "events.get",
    "links": [
      { "$ref": "#/components/links/GetEventVenue" },
      { "$ref": "#/components/links/ListEventAttendees" }
    ]
  }]
}
```

---

### components.tags

Metadata tags for logical grouping and documentation organization.

```json
{
  "components": {
    "tags": {
      "Events": {
        "name": "Events",
        "summary": "Event management functions",
        "description": "Functions for creating, reading, updating, and deleting events.",
        "externalDocs": {
          "description": "Event API Guide",
          "url": "https://docs.example.com/events"
        }
      }
    }
  }
}
```

**Tag Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Tag identifier |
| `summary` | string | No | Brief description |
| `description` | string | No | Detailed explanation (supports Markdown) |
| `externalDocs` | External Docs Object | No | Link to extended documentation |

**External Docs Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `description` | string | No | Description of the external resource |
| `url` | string | Yes | URL to the documentation |

**Usage in functions:**

```json
{
  "functions": [{
    "name": "events.list",
    "tags": [
      { "$ref": "#/components/tags/Events" }
    ]
  }]
}
```

---

### components.resources

Structured resource type definitions with attributes, relationships, and capabilities. More expressive than raw schemas — designed for domain entities with rich metadata.

**Resource Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | Resource type identifier (e.g., "events", "users") |
| `attributes` | Map[string, Attribute] | Yes | Attribute definitions keyed by name |
| `description` | string | No | Human-readable resource description |
| `relationships` | Map[string, Relationship] | No | Relationship definitions keyed by name |
| `meta` | JSON Schema | No | Schema for resource-level metadata |

#### Attribute Object

Describes a single resource attribute with capabilities.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `schema` | JSON Schema | Yes | Type definition and validation rules |
| `description` | string | No | Attribute purpose and usage |
| `filterable` | boolean | No | Can be used in filter expressions (default: false) |
| `filterOperators` | [string] | No | Supported operators when filterable |
| `sortable` | boolean | No | Can be used for sorting (default: false) |
| `sparse` | boolean | No | Can be excluded via sparse fieldsets (default: true) |
| `deprecated` | Deprecated Object | No | Deprecation information |

#### Relationship Definition Object

Describes a relationship to another resource type.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `resource` | string | Yes | Related resource type name |
| `cardinality` | string | Yes | `"one"` or `"many"` |
| `description` | string | No | Relationship purpose |
| `filterable` | boolean | No | Can filter parent by relationship (default: false) |
| `includable` | boolean | No | Can be included in responses (default: true) |
| `nested` | [string] | No | Traversable nested relationship names |

```json
{
  "components": {
    "resources": {
      "events": {
        "type": "events",
        "description": "Calendar events with venues and attendees",
        "attributes": {
          "id": {
            "schema": { "type": "string", "format": "uuid" },
            "filterable": true,
            "filterOperators": ["eq", "in"],
            "sparse": false
          },
          "name": {
            "schema": { "type": "string", "maxLength": 255 },
            "description": "Event display name",
            "filterable": true,
            "filterOperators": ["eq", "like"],
            "sortable": true
          },
          "starts_at": {
            "schema": { "type": "string", "format": "date-time" },
            "filterable": true,
            "filterOperators": ["eq", "gt", "lt", "gte", "lte"],
            "sortable": true
          },
          "status": {
            "schema": { "type": "string", "enum": ["draft", "published", "cancelled"] },
            "filterable": true,
            "filterOperators": ["eq", "in"]
          }
        },
        "relationships": {
          "venue": {
            "resource": "venues",
            "cardinality": "one",
            "description": "Event location",
            "includable": true
          },
          "attendees": {
            "resource": "users",
            "cardinality": "many",
            "description": "Registered attendees",
            "includable": true,
            "filterable": true,
            "nested": ["profile"]
          }
        }
      }
    }
  }
}
```

---

## Reference Objects

Any component can be referenced using the `$ref` keyword with a JSON Pointer:

```json
{ "$ref": "#/components/schemas/EventResource" }
{ "$ref": "#/components/contentDescriptors/EventId" }
{ "$ref": "#/components/errors/NotFound" }
{ "$ref": "#/components/examples/PublishedEvent" }
{ "$ref": "#/components/examplePairings/GetSingleEvent" }
{ "$ref": "#/components/links/GetEventVenue" }
{ "$ref": "#/components/tags/Events" }
{ "$ref": "#/components/resources/events" }
```

References can be used anywhere the corresponding object type is expected:
- `arguments[]` accepts Content Descriptor or Reference
- `result` accepts Content Descriptor or Reference
- `errors[]` accepts Error Object or Reference
- `examples[]` accepts Example Pairing or Reference
- `links[]` accepts Link Object or Reference
- `tags[]` accepts Tag Object or Reference
- `resources` can be referenced for result type documentation

---

## Function Object

Functions describe individual API operations. This section details all available fields.

**Function Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Unique function identifier |
| `version` | string | Yes | Semantic version of this function |
| `stability` | string | No | Stability level: `experimental`, `stable`, `deprecated` |
| `summary` | string | No | Brief one-line description |
| `description` | string | No | Detailed explanation (supports Markdown) |
| `arguments` | array | No | Function parameters (Content Descriptor or Reference) |
| `result` | Content Descriptor | No | Return value specification |
| `errors` | array | No | Possible errors (Error Object or Reference) |
| `tags` | array | No | Logical grouping (Tag Object or Reference) |
| `links` | array | No | Related functions (Link Object or Reference) |
| `examples` | array | No | Usage examples (Example Pairing or Reference) |
| `query` | Query Capabilities | No | Supported query operations (filters, sorts, pagination) |
| `sideEffects` | [string] | No | Declared side effects (e.g., "sends_email", "triggers_webhook") |
| `deprecated` | Deprecated Object | No | Deprecation information with reason and sunset date |
| `discoverable` | boolean | No | Whether function appears in discovery (default: true) |
| `simulations` | [Simulation Scenario] | No | Predefined scenarios for sandbox/demo mode |
| `extensions` | array | No | Extension-specific configuration |
| `externalDocs` | External Docs | No | Link to extended documentation |

### Deprecated Object

Rich deprecation information with migration guidance and removal timeline.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `reason` | string | No | Why deprecated and what to use instead |
| `sunset` | string | No | Removal date in ISO 8601 format (e.g., "2025-12-31") |

```json
{
  "deprecated": {
    "reason": "Use users.create instead",
    "sunset": "2025-06-30"
  }
}
```

### Side Effects

Array of strings declaring effects the function may cause. Helps clients understand what actions a function triggers beyond returning data.

```json
{
  "sideEffects": [
    "sends_email",
    "triggers_webhook",
    "creates_audit_log",
    "invalidates_cache"
  ]
}
```

Common side effect values:
- `sends_email` - Sends email notifications
- `sends_sms` - Sends SMS messages
- `triggers_webhook` - Fires webhook callbacks
- `creates_audit_log` - Records audit trail entries
- `invalidates_cache` - Clears cached data
- `schedules_job` - Queues background jobs
- `external_api_call` - Calls third-party APIs

### Discoverable

Controls whether a function appears in discovery responses. Defaults to `true`.

Set to `false` to hide internal or deprecated functions from standard client discovery while keeping them callable for backward compatibility.

```json
{
  "name": "internal.migrate_legacy_data",
  "discoverable": false
}
```

### Query Capabilities

Describes what query operations a function supports for filtering, sorting, pagination, field selection, and relationship inclusion.

**Query Capabilities Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `filters` | Filters Capability | No | Filtering configuration |
| `sorts` | Sorts Capability | No | Sorting configuration |
| `fields` | Fields Capability | No | Sparse fieldset configuration |
| `relationships` | Relationships Capability | No | Relationship inclusion configuration |
| `pagination` | Pagination Capability | No | Pagination configuration |

#### Filters Capability

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `allowed` | [string] | No | Filterable field names |
| `operators` | [string] | No | Supported operators (eq, neq, gt, gte, lt, lte, in, like) |
| `maxConditions` | integer | No | Maximum filter conditions per request |

#### Sorts Capability

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `allowed` | [string] | No | Sortable field names |
| `maxFields` | integer | No | Maximum sort fields per request |
| `default` | Sort Default | No | Default sort order |

#### Fields Capability

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `allowed` | [string] | No | Selectable field names |
| `default` | [string] | No | Fields returned when none specified |

#### Relationships Capability

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `allowed` | [string] | No | Includable relationship names |
| `maxDepth` | integer | No | Maximum nesting depth |

#### Pagination Capability

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `strategies` | [string] | No | Supported strategies: `offset`, `cursor`, `page` |
| `defaultSize` | integer | No | Default page size |
| `maxSize` | integer | No | Maximum page size |

```json
{
  "name": "events.list",
  "query": {
    "filters": {
      "allowed": ["status", "starts_at", "venue_id"],
      "operators": ["eq", "neq", "gt", "lt", "in"]
    },
    "sorts": {
      "allowed": ["starts_at", "name", "created_at"],
      "default": { "field": "starts_at", "direction": "asc" }
    },
    "pagination": {
      "strategies": ["offset", "cursor"],
      "defaultSize": 25,
      "maxSize": 100
    }
  }
}
```

### Simulation Scenarios

Predefined input/output pairs for sandbox and demo modes. Unlike examples (documentation), simulations are **executable** — clients can invoke functions in simulation mode to receive predictable responses without affecting real data.

**Simulation Scenario Object Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Scenario identifier (e.g., "success", "not_found") |
| `input` | object | Yes | Arguments that trigger this scenario |
| `output` | any | No | Success response (mutually exclusive with error) |
| `error` | Error Object | No | Error response (mutually exclusive with output) |
| `description` | string | No | What this scenario demonstrates |
| `metadata` | object | No | Additional response metadata (timing, headers) |

**Use Cases:**
- API explorers and interactive documentation
- Client SDK testing without backend dependencies
- Demo environments with predictable behavior
- Integration testing with known responses

```json
{
  "name": "users.create",
  "simulations": [
    {
      "name": "success",
      "description": "User successfully created",
      "input": {
        "email": "demo@example.com",
        "name": "Demo User"
      },
      "output": {
        "id": "usr_demo_001",
        "email": "demo@example.com",
        "name": "Demo User",
        "created_at": "2024-01-15T10:30:00Z"
      }
    },
    {
      "name": "duplicate_email",
      "description": "Email already registered",
      "input": {
        "email": "existing@example.com",
        "name": "Another User"
      },
      "error": {
        "code": "VALIDATION_ERROR",
        "message": "Email already exists",
        "data": { "field": "email" }
      }
    }
  ]
}
```

---

## Extension Declarations

Extensions can be declared at two levels:

### Server-Level Extensions

Declared in `servers[].extensions`, these indicate which extensions the server supports globally:

```json
{
  "servers": [
    {
      "name": "production",
      "url": "https://api.example.com/forrst",
      "extensions": [
        { "urn": "urn:forrst:ext:async", "version": "1.0.0" },
        { "urn": "urn:forrst:ext:caching", "version": "1.2.0" }
      ]
    }
  ]
}
```

### Function-Level Extensions

Declared in `functions[].extensions`, these indicate which extensions a specific function supports with optional configuration:

```json
{
  "functions": [
    {
      "name": "events.list",
      "extensions": [
        { "urn": "urn:forrst:ext:caching", "ttl": 300 },
        { "urn": "urn:forrst:ext:query", "operators": ["eq", "gt", "lt", "in"] },
        { "urn": "urn:forrst:ext:async" }
      ]
    }
  ]
}
```

**Extension Declaration Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `urn` | string | Yes | Extension URN |
| `version` | string | No | Extension version (inherits from server if omitted) |
| `*` | any | No | Extension-specific configuration |

Function-level extensions inherit the version from server-level if not specified. Additional fields are extension-specific configuration (e.g., `ttl` for caching, `operators` for query).


---

## Server Implementation

Servers implementing the discovery extension SHOULD:

1. Register all discoverable functions in the capabilities list
2. Include accurate protocol version information
3. Document all enabled extensions with versions
4. Define reusable schemas in `components.schemas`
5. Provide service limits when applicable

Servers MAY customize:

- Which functions appear in capabilities
- What limits are reported
- Extension metadata and configuration
- Schema granularity (fine-grained vs coarse)


---

## Examples

### Client Handshake Flow

```
┌──────────┐     ┌────────────────┐
│  Client  │     │    Server      │
└────┬─────┘     └───────┬────────┘
     │                   │
     │  capabilities     │
     │──────────────────▶│
     │                   │
     │  {functions, ...} │
     │◀──────────────────│
     │                   │
     │  describe(fn)     │
     │──────────────────▶│
     │                   │
     │  {schema...}      │
     │◀──────────────────│
     │                   │
     │  actual call      │
     │──────────────────▶│
     └───────────────────┘
```

### Feature Detection

```json
// Check if async is supported before using it
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_check",
  "call": {
    "function": "urn:cline:forrst:ext:discovery:fn:capabilities",
    "version": "1.0.0"
  }
}

// Response shows async is available with version
{
  "result": {
    "extensions": [
      { "urn": "urn:forrst:ext:async", "version": "1.0.0" }
    ]
  }
}
```

### Schema-Driven Client Generation

Clients can use the describe response to generate typed clients:

1. Fetch full service description
2. Parse `components.schemas` to generate model classes
3. Parse `functions` to generate method signatures
4. Resolve `$ref` pointers to link models to functions

```json
// Describe response enables generating:
// - EventResource class with typed properties
// - events.list() method returning EventResource[]
// - events.get(id: string) method returning EventResource
```
