---
title: Versioning
description: Protocol versioning and per-function versioning
---

# Versioning

> Protocol versioning and per-function versioning

---

## Overview

Forrst has two independent versioning systems, both using [Semantic Versioning](https://semver.org/):

| Type | Scope | Changes |
|------|-------|---------|
| Protocol version | Envelope structure, error format, core semantics | Rare, coordinated |
| Function version | Business logic, arguments, return shape | Per-team, independent |

All versions in Forrst MUST use semantic versioning format: `<major>.<minor>.<patch>[-<prerelease>]` (e.g., `"1.0.0"`, `"2.1.0"`, `"3.0.0-beta.1"`).

---

## Protocol Versioning

### Format

The `protocol` field specifies the Forrst protocol version using [Semantic Versioning](https://semver.org/):

```
<major>.<minor>.<patch>
```

Examples:
- `0.1.0` — Draft/experimental
- `1.0.0` — First stable release
- `1.2.0` — Minor additions
- `2.0.0` — Breaking changes

### Semantics

**Major version** — Breaking changes:
- Removing or renaming required fields
- Changing field types
- Altering error semantics
- Modifying core behavior

**Minor version** — Backwards-compatible additions:
- New optional fields
- New error codes
- New optional features

**Patch version** — Backwards-compatible fixes:
- Clarifications to specification text
- Documentation corrections
- No behavioral changes

### Compatibility Rules

Servers MUST:
- Reject requests with unsupported major versions
- Accept requests with supported major version, any minor version
- Return `INVALID_PROTOCOL_VERSION` for unsupported versions

Clients SHOULD:
- Send the protocol version they were built for
- Handle responses from any minor version of the same major

### Example

```json
// Request with unsupported version
{
  "protocol": { "name": "forrst", "version": "99.0.0" },
  "id": "req_123",
  "call": { ... }
}

// Response
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [{
    "code": "INVALID_PROTOCOL_VERSION",
    "message": "Unsupported protocol version: 99.0.0",
    "details": {
      "requested": "99.0.0",
      "supported": ["0.1.0"]
    }
  }]
}
```

---

## Function Versioning

### Philosophy

Each function is versioned independently. This differs from REST's monolithic API versioning:

**REST (monolithic):**
```
/api/v1/orders    ← Forced to v1 because users changed
/api/v1/users     ← The actual change
/api/v1/products  ← Unchanged, but still "v1"
```

**Forrst (per-function):**
```
orders.create@1.0.0   ← Untouched
users.get@2.0.0       ← Evolved independently
products.list@1.0.0   ← Untouched
```

### Benefits

- Teams evolve functions without coordinating releases
- Consumers upgrade function-by-function
- No version sprawl where everything is "v7" but 90% is unchanged
- Deprecation is surgical: sunset `orders.create@1.0.0`, not "all of v1"

### Format

The `version` field in the `call` object specifies function version:

```json
{
  "call": {
    "function": "orders.create",
    "version": "2.0.0",
    "arguments": { ... }
  }
}
```

**Type:** String. MUST use [Semantic Versioning](https://semver.org/): `"1.0.0"`, `"2.0.0"`, `"3.0.0-beta.1"`

### Prerelease Versions

Function versions support semver prerelease identifiers to indicate stability:

```
3.0.0-alpha.1   ← Alpha release
3.0.0-alpha.2   ← Second alpha
3.0.0-beta.1    ← Beta release
3.0.0-beta.2    ← Second beta
3.0.0-rc.1      ← Release candidate
3.0.0           ← Stable release (no prerelease tag)
```

**Stability** is derived from the prerelease identifier:

| Prerelease Pattern | Stability | Description |
|--------------------|-----------|-------------|
| (none) | `stable` | Production-ready, fully supported |
| `-alpha.*` | `alpha` | Early development, breaking changes expected |
| `-beta.*` | `beta` | Feature-complete, may have bugs |
| `-rc.*` | `rc` | Release candidate, final testing |

**Precedence:** Versions are ordered per semver rules. Prerelease versions have lower precedence than stable:

```
1.0.0-alpha.1 < 1.0.0-beta.1 < 1.0.0-rc.1 < 1.0.0 < 1.0.1
```

**Example request for prerelease:**

```json
{
  "call": {
    "function": "orders.create",
    "version": "3.0.0-beta.2",
    "arguments": { ... }
  }
}
```

### Default Version Behavior

When a request omits the `version` field, servers SHOULD route to the latest stable version:

```json
{
  "call": {
    "function": "orders.create",
    "arguments": { ... }
  }
}
```

**Resolution rules:**

1. Find all versions without prerelease identifiers (stable versions)
2. Select the highest stable version number
3. If no stable versions exist, return `VERSION_NOT_FOUND`

**Example:** If a function has versions `1.0.0` (deprecated), `2.0.0`, and `3.0.0-beta.1`:
- Omitting version routes to `2.0.0` (latest stable)
- Explicitly requesting `3.0.0-beta.1` routes to the beta

**Recommendations:**

- **Clients SHOULD** specify explicit versions in production code for predictability
- **Servers SHOULD** support version omission for exploration and development
- **Version discovery:** Use `urn:cline:forrst:fn:describe` to find available versions and their status

```json
// Discover versions before calling
{
  "call": {
    "function": "urn:cline:forrst:fn:describe",
    "version": "1.0.0",
    "arguments": {
      "function": "orders.create"
    }
  }
}
```

### When to Increment

Implementations MUST increment function version for:
- Removing or renaming arguments
- Changing argument types
- Changing return value structure
- Altering function behavior

Implementations SHOULD NOT increment for:
- Adding optional arguments with defaults
- Adding fields to return value
- Bug fixes that don't change the contract

### Maintaining Multiple Versions

Servers MAY support multiple versions simultaneously:

```
orders.create@1.0.0        ← Legacy, deprecated
orders.create@2.0.0        ← Current, recommended (latest stable)
orders.create@3.0.0-beta.1 ← Beta testing
orders.create@3.0.0-beta.2 ← Latest beta
```

### Deprecation

To deprecate a function version:

1. Document deprecation with timeline
2. MAY return warning in `meta`:
   ```json
   {
     "meta": {
       "deprecated": {
         "reason": "Use version 2.0.0",
         "sunset": "2025-06-01"
       }
     }
   }
   ```
3. Eventually return `VERSION_NOT_FOUND`

### Unknown Version

When a client requests an unknown version:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [{
    "code": "VERSION_NOT_FOUND",
    "message": "Version 5.0.0 not found for function orders.create",
    "details": {
      "function": "orders.create",
      "requested_version": "5.0.0",
      "available_versions": ["1.0.0", "2.0.0", "3.0.0-beta.1", "3.0.0-beta.2"]
    }
  }]
}
```

---

## Version Discovery

Clients MAY discover available versions using system functions:

```json
// Request
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_discover",
  "call": {
    "function": "urn:cline:forrst:fn:describe",
    "version": "1.0.0",
    "arguments": {
      "function": "orders.create"
    }
  }
}

// Response
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_discover",
  "result": {
    "function": "orders.create",
    "versions": [
      {
        "version": "1.0.0",
        "stability": "stable",
        "deprecated": {
          "reason": "Use version 2.0.0",
          "sunset": "2025-06-01"
        }
      },
      {
        "version": "2.0.0",
        "stability": "stable"
      },
      {
        "version": "3.0.0-beta.1",
        "stability": "beta"
      },
      {
        "version": "3.0.0-beta.2",
        "stability": "beta"
      }
    ]
  }
}
```

See [System Functions](system-functions.md) for details.

---

## Examples

### Version 1 Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_v1",
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": {
      "user_id": 42
    }
  }
}
```

### Version 1 Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_v1",
  "result": {
    "id": 42,
    "name": "Alice",
    "email": "alice@example.com"
  }
}
```

### Version 2 Request (Different Arguments)

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_v2",
  "call": {
    "function": "users.get",
    "version": "2.0.0",
    "arguments": {
      "identifier": {
        "type": "id",
        "value": 42
      }
    }
  }
}
```

### Version 2 Response (Different Structure)

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_v2",
  "result": {
    "user": {
      "id": 42,
      "profile": {
        "name": "Alice",
        "email": "alice@example.com"
      },
      "metadata": {
        "created_at": "2024-01-01T00:00:00Z"
      }
    }
  }
}
```
