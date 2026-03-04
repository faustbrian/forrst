---
title: Deprecation
description: Deprecation warnings for functions and versions
---

# Deprecation

> Deprecation warnings for functions and versions

**Extension URN:** `urn:forrst:ext:deprecation`

---

## Overview

The deprecation extension warns clients about deprecated functions, versions, or features. Servers proactively inform clients of upcoming changes, enabling smooth migrations.

---

## When to Use

Deprecation warnings SHOULD be returned when:
- A function version is scheduled for removal
- A function is being replaced by a newer alternative
- Arguments or response fields are being phased out
- Breaking changes are planned

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `acknowledge` | array | No | URIs of deprecation warnings client has acknowledged |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `warnings` | array | List of deprecation warnings |

### Warning Object

| Field | Type | Description |
|-------|------|-------------|
| `urn` | string | Unique identifier for this warning |
| `type` | string | `function`, `version`, `argument`, or `field` |
| `target` | string | What is deprecated |
| `message` | string | Human-readable explanation |
| `sunset_date` | string | ISO 8601 date when removal occurs |
| `replacement` | object | Suggested alternative |
| `documentation` | string | URL with migration guide |

---

## Behavior

When a client calls a deprecated function or version:

1. Server MUST return the normal response
2. Server MUST include deprecation warnings in extension data
3. Server SHOULD continue returning warnings until acknowledged
4. Server MAY suppress warnings for acknowledged URIs

---

## Examples

### Deprecated Version Warning

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": {
    "users": [{ "id": 1, "name": "Alice" }]
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:deprecation",
      "data": {
        "warnings": [
          {
            "urn": "deprecation:users.list:v1",
            "type": "version",
            "target": "users.list@1.0.0",
            "message": "Version 1 is deprecated. Please migrate to version 2.",
            "sunset_date": "2024-06-01",
            "replacement": {
              "function": "users.list",
              "version": "2.0.0"
            },
            "documentation": "https://api.example.com/docs/migration/users-v2"
          }
        ]
      }
    }
  ]
}
```

### Deprecated Argument Warning

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_124",
  "result": {
    "report_url": "https://..."
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:deprecation",
      "data": {
        "warnings": [
          {
            "urn": "deprecation:reports.generate:format-arg",
            "type": "argument",
            "target": "reports.generate:arguments.format",
            "message": "The 'format' argument is deprecated. Use 'output.format' instead.",
            "sunset_date": "2024-04-15",
            "replacement": {
              "argument": "output.format",
              "mapping": {
                "pdf": { "output": { "format": "pdf" } },
                "csv": { "output": { "format": "csv" } }
              }
            }
          }
        ]
      }
    }
  ]
}
```

### Multiple Warnings

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_125",
  "result": { "deleted": true },
  "extensions": [
    {
      "urn": "urn:forrst:ext:deprecation",
      "data": {
        "warnings": [
          {
            "urn": "deprecation:users.delete:v1",
            "type": "function",
            "target": "users.delete",
            "message": "users.delete is deprecated. Use users.archive for soft deletion.",
            "sunset_date": "2024-07-01",
            "replacement": {
              "function": "users.archive",
              "version": "1.0.0"
            }
          },
          {
            "urn": "deprecation:response:deleted-field",
            "type": "field",
            "target": "result.deleted",
            "message": "The 'deleted' field will be removed. Use 'status' instead.",
            "sunset_date": "2024-05-01"
          }
        ]
      }
    }
  ]
}
```

### Acknowledging Warnings

Clients can acknowledge warnings to suppress them:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_126",
  "call": {
    "function": "users.list",
    "version": "1.0.0",
    "arguments": {}
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:deprecation",
      "options": {
        "acknowledge": ["deprecation:users.list:v1"]
      }
    }
  ]
}
```

Server may omit acknowledged warnings:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_126",
  "result": {
    "users": [{ "id": 1, "name": "Alice" }]
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:deprecation",
      "data": {
        "warnings": []
      }
    }
  ]
}
```

---

## Client Behavior

Clients SHOULD:
- Log deprecation warnings
- Alert developers to upcoming changes
- Track acknowledged warnings to reduce noise
- Plan migrations before sunset dates

Clients SHOULD NOT:
- Ignore deprecation warnings
- Wait until sunset date to migrate
