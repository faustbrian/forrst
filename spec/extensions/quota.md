---
title: Quota
description: Usage quotas and limits information
---

# Quota

> Usage quotas and limits information

**Extension URN:** `urn:forrst:ext:quota`

---

## Overview

The quota extension returns remaining usage quotas and limits in responses. Helps clients track consumption and implement proactive throttling before hitting rate limits.

---

## When to Use

Quota information SHOULD be returned for:
- APIs with usage-based billing
- Rate-limited endpoints
- Resource-constrained operations
- Multi-tenant systems with per-client limits

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `include` | array | No | Specific quota types to include |

### Quota Types

| Type | Description |
|------|-------------|
| `requests` | API request quotas |
| `compute` | Compute unit quotas |
| `storage` | Storage quotas |
| `bandwidth` | Data transfer quotas |
| `custom` | Application-specific quotas |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `quotas` | array | List of quota information |

### Quota Object

| Field | Type | Description |
|-------|------|-------------|
| `type` | string | Quota type |
| `name` | string | Human-readable name |
| `limit` | number | Maximum allowed |
| `used` | number | Currently consumed |
| `remaining` | number | Available (limit - used) |
| `resets_at` | string | ISO 8601 timestamp when quota resets |
| `period` | string | `minute`, `hour`, `day`, `month`, or `billing_cycle` |
| `unit` | string | Unit of measurement |

---

## Behavior

When the quota extension is included:

1. Server MUST return current quota status
2. Server SHOULD return all applicable quotas (or those in `include`)
3. Server MUST update quota values after operation completes
4. Quota values MUST reflect post-operation state

---

## Examples

### Request with Quota Extension

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "call": {
    "function": "ai.generate",
    "version": "1.0.0",
    "arguments": {
      "prompt": "Write a haiku about APIs",
      "max_tokens": 100
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:quota",
      "options": {}
    }
  ]
}
```

### Response with Quotas

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": {
    "text": "Requests flow like streams\nJSON carries meaning far\nAPIs connect"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:quota",
      "data": {
        "quotas": [
          {
            "type": "requests",
            "name": "API Requests",
            "limit": 10000,
            "used": 4521,
            "remaining": 5479,
            "resets_at": "2024-04-01T00:00:00Z",
            "period": "month",
            "unit": "requests"
          },
          {
            "type": "compute",
            "name": "AI Tokens",
            "limit": 1000000,
            "used": 234567,
            "remaining": 765433,
            "resets_at": "2024-04-01T00:00:00Z",
            "period": "month",
            "unit": "tokens"
          }
        ]
      }
    }
  ]
}
```

### Specific Quota Types

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_124",
  "call": {
    "function": "files.upload",
    "version": "1.0.0",
    "arguments": { "filename": "report.pdf", "size": 5242880 }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:quota",
      "options": {
        "include": ["storage", "bandwidth"]
      }
    }
  ]
}
```

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_124",
  "result": {
    "file_id": "file_abc",
    "url": "https://..."
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:quota",
      "data": {
        "quotas": [
          {
            "type": "storage",
            "name": "File Storage",
            "limit": 10737418240,
            "used": 3221225472,
            "remaining": 7516192768,
            "period": "billing_cycle",
            "unit": "bytes"
          },
          {
            "type": "bandwidth",
            "name": "Monthly Transfer",
            "limit": 107374182400,
            "used": 15032385536,
            "remaining": 92341796864,
            "resets_at": "2024-04-01T00:00:00Z",
            "period": "month",
            "unit": "bytes"
          }
        ]
      }
    }
  ]
}
```

### Near-Limit Warning

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_125",
  "result": { "success": true },
  "extensions": [
    {
      "urn": "urn:forrst:ext:quota",
      "data": {
        "quotas": [
          {
            "type": "requests",
            "name": "API Requests",
            "limit": 1000,
            "used": 985,
            "remaining": 15,
            "resets_at": "2024-03-15T16:00:00Z",
            "period": "hour",
            "unit": "requests"
          }
        ]
      }
    }
  ]
}
```

### Custom Quotas

Application-specific quotas:

```json
{
  "extensions": [
    {
      "urn": "urn:forrst:ext:quota",
      "data": {
        "quotas": [
          {
            "type": "custom",
            "name": "Active Projects",
            "limit": 5,
            "used": 4,
            "remaining": 1,
            "period": "billing_cycle",
            "unit": "projects"
          },
          {
            "type": "custom",
            "name": "Team Members",
            "limit": 10,
            "used": 7,
            "remaining": 3,
            "period": "billing_cycle",
            "unit": "seats"
          }
        ]
      }
    }
  ]
}
```

---

## Client Usage

Clients SHOULD:
- Cache quota information to reduce requests
- Implement proactive throttling when approaching limits
- Display remaining quotas to users
- Alert before quotas are exhausted

```
Remaining: ████████░░ 80%  (8,000 / 10,000 requests)
Resets: 5 days
```
