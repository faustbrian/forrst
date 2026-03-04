---
title: System Functions
description: Reserved namespaces and built-in functions
---

# System Functions

> Reserved namespaces and built-in functions

---

## Overview

Function names beginning with `forrst.` are reserved for system functions. Applications MUST NOT define functions in this namespace.

System functions provide protocol-level capabilities like health checks, introspection, and operation management.

---

## Reserved Namespace

### Rules

- Function names starting with `forrst.` are reserved
- Applications MUST NOT define custom functions with this prefix
- Servers SHOULD implement core system functions
- Clients MAY call system functions like any other function

### Format

```
forrst.<category>.<action>
```

Categories:
- `urn:cline:forrst:fn:ping` — Simple connectivity check
- `urn:cline:forrst:fn:health` — Comprehensive health check
- `urn:cline:forrst:fn:describe` — Introspection
- `urn:cline:forrst:fn:capabilities` — Feature discovery
- `urn:cline:forrst:ext:atomic-lock:fn:*` — Atomic lock management (see [Atomic Lock](extensions/atomic-lock.md))

### Extension-Provided Functions

Some extensions provide their own functions within the `forrst.` namespace. These functions are only available when their parent extension is enabled:

| Extension | Functions |
|-----------|-----------|
| [Async](extensions/async.md) | `urn:cline:forrst:ext:async:fn:status`, `urn:cline:forrst:ext:async:fn:cancel`, `urn:cline:forrst:ext:async:fn:list` |
| [Replay](extensions/replay.md) | `forrst.replay.*` (if implemented) |

---

## Core System Functions

### urn:cline:forrst:fn:ping

Health check function. MUST return immediately if service is healthy.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_health",
  "call": {
    "function": "urn:cline:forrst:fn:ping",
    "version": "1.0.0",
    "arguments": {}
  }
}
```

**Response:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_health",
  "result": {
    "status": "healthy",
    "timestamp": "2024-01-15T10:30:00Z"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "tr_abc123",
        "span_id": "span_server_001",
        "duration": { "value": 1, "unit": "millisecond" }
      }
    }
  ],
  "meta": {
    "node": "orders-api-2"
  }
}
```

**Arguments:** None required

**Returns:**

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | Health status: MUST be one of `healthy`, `degraded`, `unhealthy` |
| `timestamp` | string | MUST be ISO 8601 timestamp |
| `details` | object | OPTIONAL additional health info |

---

### urn:cline:forrst:fn:health

Comprehensive health check with component-level status. Use for load balancer health checks, dependency monitoring, and operational visibility.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_health",
  "call": {
    "function": "urn:cline:forrst:fn:health",
    "version": "1.0.0",
    "arguments": {}
  }
}
```

**Response:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_health",
  "result": {
    "status": "healthy",
    "components": {
      "database": {
        "status": "healthy",
        "latency": { "value": 2, "unit": "millisecond" }
      },
      "cache": {
        "status": "healthy",
        "latency": { "value": 1, "unit": "millisecond" }
      },
      "queue": {
        "status": "healthy"
      }
    },
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `component` | string | No | Check specific component only |
| `include_details` | boolean | No | Include detailed check info (default: `true`) |

**Returns:**

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | Aggregate status (see below) |
| `components` | object | Component health status |
| `functions` | object | Function-level health (optional) |
| `timestamp` | string | ISO 8601 timestamp |
| `version` | string | Optional service version |

### Health Status Values

| Status | Description | HTTP Equivalent |
|--------|-------------|-----------------|
| `healthy` | All systems operational | 200 |
| `degraded` | Functional but impaired | 200 |
| `unhealthy` | Not able to serve requests | 503 |

### Component Check Object

Each component in `components` contains:

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | Component status |
| `latency` | object | Optional response time |
| `message` | string | Optional status message |
| `last_check` | string | Optional last successful check time |

### Aggregate Status Rules

The top-level `status` MUST reflect the worst component status:

- If ANY component is `unhealthy` → aggregate is `unhealthy`
- If ANY component is `degraded` (and none unhealthy) → aggregate is `degraded`
- If ALL components are `healthy` → aggregate is `healthy`

### Check Specific Component

```json
// Request
{
  "call": {
    "function": "urn:cline:forrst:fn:health",
    "version": "1.0.0",
    "arguments": {
      "component": "database"
    }
  }
}

// Response
{
  "result": {
    "status": "healthy",
    "components": {
      "database": {
        "status": "healthy",
        "latency": { "value": 2, "unit": "millisecond" },
        "message": "Primary connection active"
      }
    },
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

### Degraded Example

```json
{
  "result": {
    "status": "degraded",
    "components": {
      "database": {
        "status": "healthy",
        "latency": { "value": 3, "unit": "millisecond" }
      },
      "cache": {
        "status": "degraded",
        "message": "Failover to secondary, elevated latency",
        "latency": { "value": 45, "unit": "millisecond" }
      },
      "external_api": {
        "status": "healthy"
      }
    },
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

### Unhealthy Example

```json
{
  "result": {
    "status": "unhealthy",
    "components": {
      "database": {
        "status": "unhealthy",
        "message": "Connection refused",
        "last_check": "2024-01-15T10:29:55Z"
      },
      "cache": {
        "status": "healthy"
      }
    },
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

### Liveness vs Readiness

For Kubernetes-style probes, use the `component` argument:

| Probe | Component | Purpose |
|-------|-----------|---------|
| Liveness | `"self"` | Is the process alive? |
| Readiness | (omit) | Can it serve traffic? |

```json
// Liveness probe - just checks if service is running
{
  "arguments": {
    "component": "self",
    "include_details": false
  }
}

// Response
{
  "result": {
    "status": "healthy",
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

### Standard Component Names

Services SHOULD use these component names when applicable:

| Component | Description |
|-----------|-------------|
| `self` | The service process itself |
| `database` | Primary database |
| `cache` | Caching layer (Redis, Memcached) |
| `queue` | Message queue |
| `storage` | Object/file storage |
| `search` | Search engine (Elasticsearch) |
| `<service>_api` | External service dependency |

---

### Function-Level Health

Functions can report their own health status independently of component health. This enables graceful degradation where some functions remain available while others are temporarily disabled.

#### Function Health Object

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | Function status (see below) |
| `message` | string | Optional explanation |
| `until` | string | Optional ISO 8601 timestamp |
| `retry_after` | object | Optional duration before retry |

#### Function Status Values

| Status | Description |
|--------|-------------|
| `healthy` | Function is fully operational |
| `degraded` | Function works but with limitations |
| `disabled` | Function turned off (feature flag, deprecated, etc.) |
| `maintenance` | Function under scheduled maintenance |

#### Example: Function Disabled

```json
{
  "result": {
    "status": "degraded",
    "components": {
      "database": { "status": "healthy" },
      "cache": { "status": "healthy" }
    },
    "functions": {
      "reports.generate": {
        "status": "disabled",
        "message": "Disabled during maintenance window",
        "until": "2024-01-15T12:00:00Z"
      },
      "orders.create": {
        "status": "healthy"
      },
      "orders.list": {
        "status": "healthy"
      }
    },
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

#### Example: Function Degraded

```json
{
  "result": {
    "status": "degraded",
    "components": {
      "database": { "status": "healthy" },
      "external_api": { "status": "degraded" }
    },
    "functions": {
      "orders.create": {
        "status": "degraded",
        "message": "Payment validation disabled, orders require manual review"
      },
      "exports.create": {
        "status": "degraded",
        "message": "Rate limited due to high load",
        "retry_after": { "value": 30, "unit": "second" }
      }
    },
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

#### Aggregate Status with Functions

Function health contributes to aggregate status:

- If ANY function is `disabled` → aggregate is at least `degraded`
- Function `degraded` → aggregate is at least `degraded`
- Component `unhealthy` always takes precedence → aggregate is `unhealthy`

#### Calling Disabled Functions

When a function is marked `disabled`, calls to that function SHOULD return:

```json
{
  "errors": [{
    "code": "FUNCTION_DISABLED",
    "message": "Function temporarily disabled",
    "details": {
      "function": "reports.generate",
      "reason": "Feature flag disabled"
    }
  }]
}
```

#### Calling Functions Under Maintenance

When a function is marked `maintenance`, calls MUST return HTTP 503:

```json
{
  "errors": [{
    "code": "FUNCTION_MAINTENANCE",
    "message": "Function under scheduled maintenance",
    "details": {
      "function": "reports.generate",
      "reason": "Report engine upgrade",
      "until": "2024-01-15T12:00:00Z",
      "retry_after": { "value": 30, "unit": "minute" }
    }
  }]
}
```

---

### urn:cline:forrst:fn:capabilities

Discover what the service supports.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_caps",
  "call": {
    "function": "urn:cline:forrst:fn:capabilities",
    "version": "1.0.0",
    "arguments": {}
  }
}
```

**Response:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_caps",
  "result": {
    "service": "orders-api",
    "protocol_versions": ["0.1.0"],
    "extensions": [
      {
        "urn": "urn:forrst:ext:async",
        "documentation": "urn:forrst:ext:async"
      }
    ],
    "functions": [
      "orders.create",
      "orders.get",
      "orders.list",
      "orders.cancel"
    ],
    "limits": {
      "max_request_bytes": 1048576,
      "default_deadline": { "value": 30, "unit": "second" }
    }
  }
}
```

**Arguments:** None required

**Returns:**

| Field | Type | Description |
|-------|------|-------------|
| `service` | string | Service identifier |
| `protocol_versions` | array | Supported Forrst protocol versions |
| `extensions` | array | Server-wide supported extensions (see note) |
| `functions` | array | Available function names (without versions) |
| `limits` | object | Service limits |

> **Note:** Extensions listed in `urn:cline:forrst:fn:capabilities` represent server-wide defaults. Individual functions MAY restrict which extensions they accept. Use `urn:cline:forrst:fn:describe` to discover per-function extension support.

---

### urn:cline:forrst:fn:describe

Get detailed information about a specific function, including schema, side effects, and capabilities.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_describe",
  "call": {
    "function": "urn:cline:forrst:fn:describe",
    "version": "1.0.0",
    "arguments": {
      "function": "orders.create"
    }
  }
}
```

**Response:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_describe",
  "result": {
    "function": "orders.create",
    "description": "Create a new order",
    "side_effects": ["create"],
    "versions": [
      {
        "version": "1.0.0",
        "stability": "stable",
        "description": "Original version",
        "deprecated": {
          "reason": "Use version 2.0.0 for improved validation",
          "sunset": "2025-06-01"
        }
      },
      {
        "version": "2.0.0",
        "stability": "stable",
        "description": "Current version with improved validation",
        "schema": {
          "arguments": {
            "type": "object",
            "properties": {
              "customer_id": { "type": "string" },
              "items": {
                "type": "array",
                "items": {
                  "type": "object",
                  "properties": {
                    "product_id": { "type": "string" },
                    "quantity": { "type": "integer", "minimum": 1 }
                  },
                  "required": ["product_id", "quantity"]
                }
              },
              "shipping_address": { "$ref": "#/definitions/address" }
            },
            "required": ["customer_id", "items"]
          },
          "returns": {
            "type": "object",
            "properties": {
              "id": { "type": "string" },
              "status": { "type": "string", "enum": ["pending", "confirmed"] },
              "total": { "type": "number" }
            }
          },
          "definitions": {
            "address": {
              "type": "object",
              "properties": {
                "street": { "type": "string" },
                "city": { "type": "string" },
                "country_code": { "type": "string", "pattern": "^[A-Z]{2}$" }
              }
            }
          }
        }
      },
      {
        "version": "3.0.0",
        "stability": "beta",
        "description": "Beta with async support"
      }
    ],
    "recommended_version": "2.0.0"
  }
}
```

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `function` | string | Yes | Function name to describe |
| `version` | string | No | Specific version (returns all if omitted) |
| `include_schema` | boolean | No | Include JSON Schema (default: `true`) |

**Returns:**

| Field | Type | Description |
|-------|------|-------------|
| `function` | string | Function name |
| `description` | string | Human-readable description |
| `side_effects` | array | Side effects (see below). Empty array means read-only. |
| `versions` | array | Available versions with status |
| `recommended_version` | string | Suggested version to use |

### Side Effects

The `side_effects` array indicates what state changes a function may cause:

| Value | Description |
|-------|-------------|
| `create` | Creates new resources |
| `update` | Modifies existing resources |
| `delete` | Removes resources |

An empty array (`[]`) indicates a read-only function with no side effects.

### Schema Object

Each version MAY include a `schema` object with JSON Schema definitions:

| Field | Type | Description |
|-------|------|-------------|
| `arguments` | object | JSON Schema for function arguments |
| `returns` | object | JSON Schema for successful result |
| `definitions` | object | Shared schema definitions |

Schemas use [JSON Schema Draft 2020-12](https://json-schema.org/draft/2020-12/json-schema-core.html) with these extensions:
- `$ref` for referencing definitions
- Standard formats: `date-time`, `email`, `uri`, etc.

### Version Status Values

| Status | Description |
|--------|-------------|
| `stable` | Production-ready, fully supported |
| `beta` | Testing, MAY change |
| `removed` | No longer available (MAY be omitted from list) |

Deprecation is indicated by the presence of a `deprecated` object, not a status value. See [Deprecated Object](description.md#deprecated-object).

### Pagination Discovery

For list functions, `urn:cline:forrst:fn:describe` returns pagination capabilities:

```json
{
  "result": {
    "function": "orders.list",
    "description": "List orders with filtering and pagination",
    "side_effects": [],
    "versions": [
      {
        "version": "1.0.0",
        "stability": "stable",
        "pagination": {
          "styles": ["cursor", "offset"],
          "default_style": "cursor",
          "default_limit": 50,
          "max_limit": 100
        }
      }
    ],
    "recommended_version": "1.0.0"
  }
}
```

#### Pagination Object

| Field | Type | Description |
|-------|------|-------------|
| `styles` | array | Supported pagination styles |
| `default_style` | string | Style used when not specified |
| `default_limit` | integer | Items per page when not specified |
| `max_limit` | integer | Maximum allowed limit |

#### Pagination Styles

| Style | Description |
|-------|-------------|
| `cursor` | Opaque cursor-based pagination |
| `offset` | Numeric offset pagination |
| `keyset` | Key-based pagination (e.g., `after_id`) |

### Query Capabilities

For functions supporting filtering, sorting, and sparse fieldsets:

```json
{
  "result": {
    "function": "orders.list",
    "side_effects": [],
    "versions": [
      {
        "version": "1.0.0",
        "stability": "stable",
        "capabilities": {
          "filters": {
            "self": ["id", "status", "created_at", "total_amount"],
            "customer": ["id", "type", "country_code"]
          },
          "sorts": ["created_at", "total_amount", "status"],
          "fields": {
            "self": ["id", "status", "total_amount", "created_at", "updated_at"],
            "customer": ["id", "name", "email"]
          },
          "relationships": ["customer", "items", "shipments"]
        },
        "pagination": {
          "styles": ["cursor"],
          "default_limit": 50,
          "max_limit": 100
        }
      }
    ]
  }
}
```

#### Capabilities Object

| Field | Type | Description |
|-------|------|-------------|
| `filters` | object | Filterable attributes by resource |
| `sorts` | array | Sortable attributes |
| `fields` | object | Selectable fields by resource |
| `relationships` | array | Includable relationships |

### Extension Support

Functions can declare which extensions they support, overriding server-wide defaults:

```json
{
  "result": {
    "function": "orders.create",
    "description": "Create a new order",
    "side_effects": ["create"],
    "versions": [
      {
        "version": "2.0.0",
        "stability": "stable",
        "extensions": {
          "supported": ["urn:forrst:ext:idempotency", "urn:forrst:ext:dry-run", "urn:forrst:ext:tracing"]
        }
      }
    ]
  }
}
```

#### Extensions Object

| Field | Type | Description |
|-------|------|-------------|
| `supported` | array | Extensions this function accepts (allowlist) |
| `excluded` | array | Extensions this function rejects (blocklist) |

#### Rules

- If `extensions` is omitted → function supports all server-wide extensions
- If `supported` is present → only listed extensions are accepted
- If `excluded` is present → server-wide extensions minus these are accepted
- MUST NOT specify both `supported` and `excluded`

#### Example: Excluding Extensions

```json
{
  "result": {
    "function": "orders.list",
    "description": "List orders",
    "side_effects": [],
    "versions": [
      {
        "version": "1.0.0",
        "stability": "stable",
        "extensions": {
          "excluded": ["urn:forrst:ext:idempotency", "urn:forrst:ext:dry-run"]
        }
      }
    ]
  }
}
```

#### Unsupported Extension Error

When a client requests an extension that a function doesn't support:

```json
{
  "errors": [{
    "code": "EXTENSION_NOT_APPLICABLE",
    "message": "Caching extension not applicable to write operations",
    "source": { "pointer": "/extensions/0" },
    "details": {
      "extension": "urn:forrst:ext:caching",
      "function": "orders.create"
    }
  }]
}
```

See [Errors](errors.md) for the full error specification.

---

## Lock Management Functions

System functions for managing atomic locks acquired via the [Atomic Lock extension](extensions/atomic-lock.md). These functions enable cross-process lock release and status monitoring.

### urn:cline:forrst:ext:atomic-lock:fn:release

Release a lock with ownership verification. Requires the owner token from lock acquisition.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_lock_release",
  "call": {
    "function": "urn:cline:forrst:ext:atomic-lock:fn:release",
    "version": "1.0.0",
    "arguments": {
      "key": "forrst_lock:payments.charge:user:123",
      "owner": "550e8400-e29b-41d4-a716-446655440000"
    }
  }
}
```

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | Yes | Full lock key (with scope prefix) |
| `owner` | string | Yes | Owner token from lock acquisition |

**Returns:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_lock_release",
  "result": {
    "released": true,
    "key": "forrst_lock:payments.charge:user:123"
  }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `released` | boolean | Whether release was successful |
| `key` | string | The lock key that was released |

**Errors:**

| Code | Description |
|------|-------------|
| `LOCK_NOT_FOUND` | Lock does not exist or has expired |
| `LOCK_OWNERSHIP_MISMATCH` | Provided owner token does not match |

---

### urn:cline:forrst:ext:atomic-lock:fn:force-release

Administratively release a lock without ownership verification. Use with caution as this can interrupt in-progress operations.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_lock_force",
  "call": {
    "function": "urn:cline:forrst:ext:atomic-lock:fn:force-release",
    "version": "1.0.0",
    "arguments": {
      "key": "forrst_lock:payments.charge:user:123"
    }
  }
}
```

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | Yes | Full lock key (with scope prefix) |

**Returns:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_lock_force",
  "result": {
    "released": true,
    "key": "forrst_lock:payments.charge:user:123",
    "forced": true
  }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `released` | boolean | Whether release was successful |
| `key` | string | The lock key that was released |
| `forced` | boolean | Always `true` for force release |

**Errors:**

| Code | Description |
|------|-------------|
| `LOCK_NOT_FOUND` | Lock does not exist |

---

### urn:cline:forrst:ext:atomic-lock:fn:status

Check the status of a lock. Useful for debugging lock contention and monitoring.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_lock_status",
  "call": {
    "function": "urn:cline:forrst:ext:atomic-lock:fn:status",
    "version": "1.0.0",
    "arguments": {
      "key": "forrst_lock:payments.charge:user:123"
    }
  }
}
```

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | Yes | Full lock key (with scope prefix) |

**Returns (locked):**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_lock_status",
  "result": {
    "key": "forrst_lock:payments.charge:user:123",
    "locked": true,
    "owner": "550e8400-e29b-41d4-a716-446655440000",
    "acquired_at": "2024-01-15T10:30:00Z",
    "expires_at": "2024-01-15T10:30:30Z",
    "ttl_remaining": 25
  }
}
```

**Returns (unlocked):**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_lock_status",
  "result": {
    "key": "forrst_lock:payments.charge:user:123",
    "locked": false
  }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `key` | string | The lock key |
| `locked` | boolean | Whether the lock is currently held |
| `owner` | string | Owner token (only if locked) |
| `acquired_at` | string | ISO 8601 acquisition timestamp (only if locked) |
| `expires_at` | string | ISO 8601 expiration timestamp (only if locked) |
| `ttl_remaining` | integer | Seconds until lock expires (only if locked) |

**Errors:** None — status check always succeeds

---

## Future Reserved Namespaces

These namespaces are reserved for potential future use:

| Namespace | Intended Purpose |
|-----------|------------------|
| `forrst.auth.*` | Authentication functions |
| `forrst.schema.*` | Schema introspection |
| `forrst.metrics.*` | Metrics and observability |
| `forrst.config.*` | Configuration discovery |

---

## Implementation Requirements

### Required Functions

Servers SHOULD implement:
- `urn:cline:forrst:fn:ping` — Simple connectivity check
- `urn:cline:forrst:fn:health` — Comprehensive health check

### Recommended Functions

Servers SHOULD implement if applicable:
- `urn:cline:forrst:fn:capabilities` — For service discovery
- `urn:cline:forrst:fn:describe` — For API exploration
- `urn:cline:forrst:ext:atomic-lock:fn:*` — If supporting atomic lock extension

### Extension-Provided Functions

When enabling extensions that provide functions, servers MUST register those functions:
- `urn:cline:forrst:ext:async:fn:*` — Provided by [Async](extensions/async.md) extension

### Ping vs Health

| Function | Purpose | Use Case |
|----------|---------|----------|
| `urn:cline:forrst:fn:ping` | Fast connectivity check | Heartbeats, basic liveness |
| `urn:cline:forrst:fn:health` | Dependency-aware health | Load balancers, readiness probes |

Use `urn:cline:forrst:fn:ping` when you just need to know if the service is reachable. Use `urn:cline:forrst:fn:health` when you need to know if the service can actually serve requests (dependencies are healthy).

### Custom System Functions

Organizations MAY define additional system functions within their own namespace:

```
myorg.system.audit
myorg.system.config
```

These MUST NOT use the `forrst.` prefix.

---

## Examples

### Health Check

```json
// Request
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "health_001",
  "call": {
    "function": "urn:cline:forrst:fn:ping",
    "version": "1.0.0"
  }
}

// Response
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "health_001",
  "result": {
    "status": "healthy",
    "timestamp": "2024-01-15T10:30:00Z",
    "details": {
      "database": "connected",
      "cache": "connected",
      "queue": "connected"
    }
  }
}
```

### Function Discovery

```json
// Request
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "discover_001",
  "call": {
    "function": "urn:cline:forrst:fn:capabilities",
    "version": "1.0.0"
  }
}

// Response
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "discover_001",
  "result": {
    "service": "user-service",
    "protocol_versions": ["0.1.0"],
    "extensions": [
      { "urn": "urn:forrst:ext:async" }
    ],
    "functions": [
      "users.create",
      "users.get",
      "users.update",
      "users.delete",
      "users.list"
    ],
    "limits": {
      "default_deadline": { "value": 10, "unit": "second" }
    }
  }
}
```

### Degraded Health

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "health_002",
  "result": {
    "status": "degraded",
    "timestamp": "2024-01-15T10:30:00Z",
    "details": {
      "database": "connected",
      "cache": "disconnected",
      "queue": "connected"
    },
    "message": "Cache unavailable, operating with degraded performance"
  }
}
```
