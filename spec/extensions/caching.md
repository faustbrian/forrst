---
title: Caching
description: Cache control and conditional requests
---

# Caching

> Cache control and conditional requests

**Extension URN:** `urn:forrst:ext:caching`

---

## Overview

The caching extension enables ETags, cache hints, and conditional requests following [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110) Section 8.8 (Conditional Requests) and [RFC 9111](https://www.rfc-editor.org/rfc/rfc9111) (HTTP Caching). Servers provide cache metadata; clients can make conditional requests to avoid unnecessary data transfer.

---

## When to Use

Caching SHOULD be used for:
- Read-heavy APIs
- Large response payloads
- Resources that change infrequently
- Bandwidth-sensitive clients

Caching SHOULD NOT be used for:
- Real-time data requirements
- Frequently changing resources
- Write operations

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `if_none_match` | string | No | ETag value for conditional request |
| `if_modified_since` | string | No | ISO 8601 timestamp for conditional request |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `etag` | string | Entity tag for this response |
| `max_age` | object | Duration this response can be cached |
| `last_modified` | string | ISO 8601 timestamp of last modification |
| `cache_status` | string | `hit`, `miss`, `stale`, or `bypass` |

---

## Behavior

### Standard Response

When the caching extension is included without conditions:

1. Server MUST return `etag` if resource is cacheable
2. Server SHOULD return `max_age` hint
3. Server MAY return `last_modified`

### Conditional Request (ETag Match)

When `if_none_match` matches current ETag:

1. Server MUST return `result: null`
2. Server MUST include `cache_status: "hit"` in extension data
3. Client SHOULD use cached response

### Conditional Request (ETag Mismatch)

When `if_none_match` does not match:

1. Server MUST return full response
2. Server MUST include new `etag`
3. Server SHOULD include `cache_status: "miss"`

---

## Examples

### Initial Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "call": {
    "function": "products.get",
    "version": "1.0.0",
    "arguments": { "product_id": 42 }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:caching",
      "options": {}
    }
  ]
}
```

### Initial Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": {
    "product_id": 42,
    "name": "Widget Pro",
    "price": { "amount": 99.99, "currency": "USD" },
    "inventory": 150
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:caching",
      "data": {
        "etag": "\"a1b2c3d4\"",
        "max_age": { "value": 300, "unit": "second" },
        "last_modified": "2024-03-15T10:30:00Z",
        "cache_status": "miss"
      }
    }
  ]
}
```

### Conditional Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_124",
  "call": {
    "function": "products.get",
    "version": "1.0.0",
    "arguments": { "product_id": 42 }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:caching",
      "options": {
        "if_none_match": "\"a1b2c3d4\""
      }
    }
  ]
}
```

### Not Modified Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_124",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:caching",
      "data": {
        "etag": "\"a1b2c3d4\"",
        "cache_status": "hit"
      }
    }
  ]
}
```

### Modified Response

When resource has changed:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_125",
  "result": {
    "product_id": 42,
    "name": "Widget Pro",
    "price": { "amount": 89.99, "currency": "USD" },
    "inventory": 75
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:caching",
      "data": {
        "etag": "\"e5f6g7h8\"",
        "max_age": { "value": 300, "unit": "second" },
        "last_modified": "2024-03-16T14:20:00Z",
        "cache_status": "miss"
      }
    }
  ]
}
```

---

## ETag Format

ETags MUST be per [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110) Section 8.8.3:
- Quoted strings (entity-tag format)
- Unique per resource version
- Opaque to clients

Examples:
- `"a1b2c3d4"` — Hash-based
- `"v42"` — Version-based
- `"1710512400"` — Timestamp-based
