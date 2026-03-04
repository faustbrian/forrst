---
title: URN Naming
description: Uniform Resource Name conventions for Forrst identifiers
---

# URN Naming

> Uniform Resource Name conventions for Forrst identifiers

---

## Overview

Forrst uses URNs (Uniform Resource Names) per [RFC 8141](https://www.rfc-editor.org/rfc/rfc8141) to uniquely identify extensions and functions. URNs provide globally unique, vendor-namespaced identifiers that prevent collisions in distributed systems.

---

## URN Structure

### General Format

```
urn:<vendor>:forrst:<type>:<name>
```

| Segment | Description | Example |
|---------|-------------|---------|
| `urn:` | URN scheme prefix (required) | `urn:` |
| `<vendor>` | Vendor/organization identifier | `cline`, `acme`, `stripe` |
| `forrst:` | Protocol identifier | `forrst:` |
| `<type>` | Resource type | `ext`, `fn` |
| `<name>` | Resource name (kebab-case) | `async`, `orders:create` |

### Examples

All URNs follow the same vendor pattern—core Forrst uses `cline` (the package vendor):

```
urn:cline:forrst:ext:async         ← Core extension
urn:cline:forrst:fn:ping           ← Core function
urn:cline:forrst:fn:capabilities   ← Core function
urn:acme:forrst:ext:audit          ← Third-party extension
urn:stripe:forrst:fn:charges:create  ← Third-party function
```

---

## Extension URNs

Extensions MUST use URNs. The type segment is `ext`:

```
urn:<vendor>:forrst:ext:<extension-name>
```

### Examples

```
urn:cline:forrst:ext:async           ← Async operations (core)
urn:cline:forrst:ext:caching         ← Response caching (core)
urn:cline:forrst:ext:rate-limit      ← Rate limiting (core)
urn:acme:forrst:ext:audit      ← Vendor audit extension
urn:acme:forrst:ext:workflow   ← Vendor workflow extension
```

### Rules

1. Extension names MUST be kebab-case
2. Extension names MUST be lowercase
3. Vendor identifiers SHOULD be short (3-15 characters)
4. Vendor identifiers MUST be lowercase alphanumeric

---

## Function URNs

Functions SHOULD use URNs for consistency with extensions. The type segment is `fn`:

```
urn:<vendor>:forrst:fn:<function-name>
```

### Core System Functions

```
urn:cline:forrst:fn:ping
urn:cline:forrst:fn:capabilities
urn:cline:forrst:fn:describe
urn:cline:forrst:fn:health
```

### Application Functions

```
urn:acme:forrst:fn:orders:create
urn:acme:forrst:fn:orders:get
urn:acme:forrst:fn:orders:list
urn:acme:forrst:fn:users:authenticate
```

### Extension-Provided Functions

Extensions MAY provide additional functions. These are scoped under the extension:

```
urn:cline:forrst:ext:async:fn:status
urn:cline:forrst:ext:async:fn:cancel
urn:cline:forrst:ext:async:fn:list
urn:cline:forrst:ext:atomic-lock:fn:status
urn:cline:forrst:ext:atomic-lock:fn:release
urn:cline:forrst:ext:atomic-lock:fn:force-release
urn:cline:forrst:ext:cancellation:fn:cancel
```

Format: `urn:<vendor>:forrst:ext:<extension>:fn:<function>`

---

## Naming Rules

### Kebab-Case

All URN segments after the type MUST use kebab-case:

```
urn:cline:forrst:fn:force-release     ✓ Correct
urn:cline:forrst:fn:forceRelease      ✗ Incorrect (camelCase)
urn:cline:forrst:fn:force_release     ✗ Incorrect (snake_case)
```

### Hierarchical Names

Use colons to separate hierarchical segments within names:

```
urn:cline:forrst:ext:async:fn:status     ← operation.status equivalent
urn:cline:forrst:ext:atomic-lock:fn:force-release  ← locks.forceRelease equivalent
urn:acme:forrst:fn:orders:items:add
```

### Reserved Namespaces

The `cline` vendor namespace is reserved for core protocol functions and extensions:

```
urn:cline:forrst:*    ← Reserved for core Forrst
```

Vendors MUST NOT use `cline` as their vendor identifier.

---

## Compliance Levels

### MUST (Required)

- Extensions MUST use URN format
- URNs MUST follow RFC 8141 syntax
- Vendor identifiers MUST be lowercase alphanumeric
- Reserved namespace `cline` MUST NOT be used by vendors

### SHOULD (Recommended)

- Functions SHOULD use URN format
- URN names SHOULD use kebab-case
- Extension-provided functions SHOULD be scoped under the extension URN

---

## Discovery

### Extension Discovery

```json
{
  "call": {
    "function": "urn:cline:forrst:fn:capabilities",
    "version": "1.0.0"
  }
}
```

Response includes extension URNs:

```json
{
  "result": {
    "extensions": [
      { "urn": "urn:cline:forrst:ext:async" },
      { "urn": "urn:cline:forrst:ext:caching" },
      { "urn": "urn:acme:forrst:ext:audit" }
    ]
  }
}
```

### Function Discovery

Use `urn:cline:forrst:fn:describe` to discover function URNs:

```json
{
  "call": {
    "function": "urn:cline:forrst:fn:describe",
    "version": "1.0.0",
    "arguments": {
      "function": "urn:acme:forrst:fn:orders:create"
    }
  }
}
```

---

## Examples

### Request with URN Function

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "call": {
    "function": "urn:acme:forrst:fn:orders:create",
    "version": "1.0.0",
    "arguments": {
      "customer_id": "cus_123",
      "items": [{ "sku": "WIDGET-01", "quantity": 2 }]
    }
  },
  "extensions": [
    {
      "urn": "urn:cline:forrst:ext:idempotency",
      "options": { "key": "order-abc-123" }
    }
  ]
}
```

### Extension-Provided Function

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_002",
  "call": {
    "function": "urn:cline:forrst:ext:atomic-lock:fn:acquire",
    "version": "1.0.0",
    "arguments": {
      "resource": "inventory:sku-123",
      "ttl": { "value": 30, "unit": "second" }
    }
  }
}
```

### Multi-Vendor System

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_003",
  "call": {
    "function": "urn:payments:forrst:fn:charges:create",
    "version": "1.0.0",
    "arguments": {
      "amount": { "value": "99.99", "currency": "USD" }
    }
  },
  "extensions": [
    { "urn": "urn:cline:forrst:ext:idempotency", "options": { "key": "charge-xyz" } },
    { "urn": "urn:acme:forrst:ext:audit", "options": { "actor": "user_42" } }
  ]
}
```

---

## Validation

### URN Regex Pattern

```regex
^urn:[a-z][a-z0-9-]*:forrst:(ext|fn)(:[a-z][a-z0-9-]*)+$
```

### Validation Rules

1. MUST start with `urn:`
2. Vendor MUST be lowercase, start with letter
3. MUST contain `:forrst:`
4. Type MUST be `ext` or `fn`
5. Name segments MUST be kebab-case, start with letter
6. MUST have at least one name segment after type
