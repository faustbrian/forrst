---
title: Replay
description: Request journaling for deferred execution
---

# Replay

> Request journaling for deferred execution

**Extension URN:** `urn:forrst:ext:replay`

---

## Overview

The replay extension enables servers to record requests that cannot be processed immediately, allowing them to be replayed when the system recovers. This differs from [Idempotency](idempotency.md) which caches *results* to prevent duplicates—replay records *requests* for later execution.

Key use cases:
- Requests during maintenance windows
- Requests that fail due to transient errors
- Requests rejected due to capacity limits
- Graceful degradation during outages

---

## When to Use

Replay SHOULD be used for:
- Operations that must eventually succeed (order placement, payments)
- Requests during scheduled maintenance
- High-value operations that shouldn't be lost
- Systems requiring guaranteed delivery

Replay SHOULD NOT be used for:
- Read operations (query fresh data instead)
- Time-sensitive operations (prices change, inventory depletes)
- Operations requiring immediate user feedback
- Requests with tight deadlines

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | No | Enable replay for this request (default: `true` if extension present) |
| `ttl` | object | No | Maximum time to retain for replay (value/unit) |
| `priority` | string | No | Replay priority: `high`, `normal`, `low` |
| `callback` | object | No | Webhook to notify on replay completion |

### Callback Object

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `url` | string | Yes | Webhook URL for completion notification |
| `headers` | object | No | Headers to include in callback request |

---

## Data (Response)

### Successful Processing

When request is processed normally:

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | `processed` |
| `replay_id` | string | Unique ID (for reference, not replayed) |

### Queued for Replay

When request is queued for later:

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | `queued` |
| `replay_id` | string | Unique ID to track replay status |
| `reason` | string | Why request was queued |
| `queued_at` | string | ISO 8601 timestamp |
| `expires_at` | string | When request will be discarded if not replayed |
| `position` | integer | Queue position (optional) |
| `estimated_replay` | object | Estimated time until replay (value/unit) |

---

## Replay Statuses

| Status | Description |
|--------|-------------|
| `queued` | Request recorded, awaiting replay |
| `processing` | Currently being replayed |
| `completed` | Successfully replayed |
| `failed` | Replay attempted but failed permanently |
| `expired` | TTL exceeded, request discarded |
| `cancelled` | Manually cancelled |

---

## Behavior

### Request Queuing

When a request cannot be processed and replay is enabled:

1. Server MUST record the complete request
2. Server MUST generate a unique `replay_id`
3. Server MUST return `status: "queued"` in extension data
4. Server MUST NOT return an error (request was accepted)
5. Server SHOULD return HTTP 202 Accepted

### Automatic Replay

When the system recovers:

1. Server MUST replay queued requests in order (respecting priority)
2. Server MUST respect original idempotency keys (if present)
3. Server MUST update replay status on completion
4. Server SHOULD notify via callback (if configured)

### Manual Replay

Clients can trigger replay via system functions (see below).

---

## Examples

### Request with Replay

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "call": {
    "function": "orders.create",
    "version": "1.0.0",
    "arguments": {
      "customer_id": "cust_456",
      "items": [{ "sku": "WIDGET-01", "quantity": 2 }]
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:replay",
      "options": {
        "ttl": { "value": 24, "unit": "hour" },
        "priority": "high",
        "callback": {
          "url": "https://api.example.com/webhooks/replay",
          "headers": {
            "Authorization": "Bearer webhook_token_xyz"
          }
        }
      }
    },
    {
      "urn": "urn:forrst:ext:idempotency",
      "options": {
        "key": "order_cust456_20240115"
      }
    }
  ]
}
```

### Queued Response (During Maintenance)

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:replay",
      "data": {
        "status": "queued",
        "replay_id": "rpl_abc789",
        "reason": "SERVER_MAINTENANCE",
        "queued_at": "2024-01-15T10:30:00Z",
        "expires_at": "2024-01-16T10:30:00Z",
        "estimated_replay": { "value": 2, "unit": "hour" }
      }
    },
    {
      "urn": "urn:forrst:ext:maintenance",
      "data": {
        "scope": "server",
        "reason": "Database migration",
        "until": "2024-01-15T12:00:00Z"
      }
    }
  ],
  "meta": {
    "accepted": true
  }
}
```

Note: No `errors` array—the request was accepted for replay.

### Processed Normally

When request succeeds without queuing:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_456",
  "result": {
    "order_id": "ord_xyz",
    "status": "created"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:replay",
      "data": {
        "status": "processed",
        "replay_id": "rpl_def123"
      }
    }
  ]
}
```

### Failed After Replay

When a replayed request fails permanently:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_789",
  "result": null,
  "errors": [{
    "code": "INVALID_ARGUMENTS",
    "message": "Customer account closed",
    "details": {
      "customer_id": "cust_456",
      "reason": "Account closed during maintenance window"
    }
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:replay",
      "data": {
        "status": "failed",
        "replay_id": "rpl_abc789",
        "queued_at": "2024-01-15T10:30:00Z",
        "replayed_at": "2024-01-15T12:30:00Z",
        "attempts": 1
      }
    }
  ]
}
```

---

## HTTP Mapping

| Scenario | HTTP Status | Description |
|----------|-------------|-------------|
| Processed normally | 200 OK | Request completed |
| Queued for replay | 202 Accepted | Request accepted, will be processed later |
| Replay completed | 200 OK | Replayed request succeeded |
| Replay failed | 4xx/5xx | Replayed request failed |

---

## System Functions

### forrst.replay.status

Check status of a queued request:

```json
{
  "call": {
    "function": "forrst.replay.status",
    "version": "1.0.0",
    "arguments": {
      "replay_id": "rpl_abc789"
    }
  }
}
```

**Response:**

```json
{
  "result": {
    "replay_id": "rpl_abc789",
    "status": "queued",
    "original_request_id": "req_123",
    "function": "orders.create",
    "version": "1.0.0",
    "queued_at": "2024-01-15T10:30:00Z",
    "expires_at": "2024-01-16T10:30:00Z",
    "position": 42,
    "estimated_replay": { "value": 30, "unit": "minute" }
  }
}
```

### forrst.replay.cancel

Cancel a queued request:

```json
{
  "call": {
    "function": "forrst.replay.cancel",
    "version": "1.0.0",
    "arguments": {
      "replay_id": "rpl_abc789"
    }
  }
}
```

**Response:**

```json
{
  "result": {
    "replay_id": "rpl_abc789",
    "status": "cancelled",
    "cancelled_at": "2024-01-15T11:00:00Z"
  }
}
```

### forrst.replay.list

List queued requests:

```json
{
  "call": {
    "function": "forrst.replay.list",
    "version": "1.0.0",
    "arguments": {
      "status": "queued",
      "function": "orders.create",
      "limit": 50
    }
  }
}
```

**Response:**

```json
{
  "result": {
    "replays": [
      {
        "replay_id": "rpl_abc789",
        "function": "orders.create",
        "status": "queued",
        "queued_at": "2024-01-15T10:30:00Z",
        "reason": "SERVER_MAINTENANCE"
      },
      {
        "replay_id": "rpl_def456",
        "function": "orders.create",
        "status": "queued",
        "queued_at": "2024-01-15T10:31:00Z",
        "reason": "SERVER_MAINTENANCE"
      }
    ],
    "total": 127,
    "next_cursor": "eyJpZCI6InJwbF9kZWY0NTYifQ=="
  }
}
```

### forrst.replay.trigger

Manually trigger replay of a specific request:

```json
{
  "call": {
    "function": "forrst.replay.trigger",
    "version": "1.0.0",
    "arguments": {
      "replay_id": "rpl_abc789"
    }
  }
}
```

**Response:**

```json
{
  "result": {
    "replay_id": "rpl_abc789",
    "status": "processing",
    "triggered_at": "2024-01-15T11:30:00Z"
  }
}
```

---

## Callback Notifications

When a callback URL is configured, the server sends a webhook on completion:

### Callback Request

```json
POST /webhooks/replay HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer webhook_token_xyz

{
  "event": "replay.completed",
  "timestamp": "2024-01-15T12:30:00Z",
  "data": {
    "replay_id": "rpl_abc789",
    "status": "completed",
    "original_request_id": "req_123",
    "function": "orders.create",
    "result": {
      "order_id": "ord_xyz",
      "status": "created"
    },
    "queued_at": "2024-01-15T10:30:00Z",
    "replayed_at": "2024-01-15T12:30:00Z"
  }
}
```

### Callback Events

| Event | Description |
|-------|-------------|
| `replay.completed` | Request replayed successfully |
| `replay.failed` | Request failed after replay |
| `replay.expired` | Request expired without replay |

---

## Idempotency Integration

Replay works with the [Idempotency](idempotency.md) extension:

1. If request includes idempotency key, it's preserved in replay
2. When replayed, idempotency key is checked first
3. If already processed (by another path), cached result is returned
4. This prevents duplicates from queued + direct retry scenarios

```json
{
  "extensions": [
    {
      "urn": "urn:forrst:ext:replay",
      "options": { "enabled": true }
    },
    {
      "urn": "urn:forrst:ext:idempotency",
      "options": { "key": "order_cust456_20240115" }
    }
  ]
}
```

---

## Server Implementation

### Storage Requirements

Servers MUST store:
- Complete request envelope (protocol, id, call, extensions, context)
- Replay metadata (id, status, timestamps, reason)
- Callback configuration

### Recommended Schema

```
replay_queue {
  replay_id: string (primary)
  original_request_id: string
  function: string
  version: string
  request_payload: json
  idempotency_key: string (nullable)
  status: enum(queued, processing, completed, failed, expired, cancelled)
  reason: string
  priority: enum(high, normal, low)
  callback_url: string (nullable)
  callback_headers: json (nullable)
  queued_at: timestamp
  expires_at: timestamp
  replayed_at: timestamp (nullable)
  result: json (nullable)
  error: json (nullable)
  attempts: integer
}
```

### Processing Order

Replays SHOULD be processed in order:

1. Priority (high → normal → low)
2. Queue time (oldest first within priority)

### Retry Policy

For transient failures during replay:

1. Retry with exponential backoff
2. Maximum 3 attempts per replay
3. Mark as `failed` after max attempts
4. Include attempt count in response

---

## Error Codes

| Code | Retryable | Description |
|------|-----------|-------------|
| `REPLAY_NOT_FOUND` | No | Unknown replay ID |
| `REPLAY_EXPIRED` | No | Request TTL exceeded |
| `REPLAY_ALREADY_COMPLETE` | No | Request already replayed |
| `REPLAY_CANCELLED` | No | Request was cancelled |

---

## Related Extensions

- [Maintenance](maintenance.md) — Triggers replay queuing
- [Idempotency](idempotency.md) — Prevents duplicate processing
- [Async](async.md) — Long-running operations (different from replay)
- [Priority](priority.md) — Request priority hints
