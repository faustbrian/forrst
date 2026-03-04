---
title: Priority
description: Request priority hints for queue management
---

# Priority

> Request priority hints for queue management

**Extension URN:** `urn:forrst:ext:priority`

---

## Overview

The priority extension allows clients to hint request urgency. Servers can use these hints for queue management, resource allocation, and request scheduling.

---

## When to Use

Priority hints SHOULD be used for:
- Background vs interactive requests
- Batch processing vs real-time operations
- System tasks vs user-facing requests
- Degraded mode operation (prioritize critical requests)

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `level` | string | Yes | Priority level |
| `reason` | string | No | Why this priority was chosen |

### Priority Levels

| Level | Description |
|-------|-------------|
| `critical` | System-critical, highest priority |
| `high` | User-facing, time-sensitive |
| `normal` | Standard priority (default) |
| `low` | Background tasks, can be delayed |
| `bulk` | Batch operations, lowest priority |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `honored` | boolean | Whether priority was applied |
| `effective_level` | string | Actual priority used |
| `queue_position` | number | Position in queue (if queued) |
| `wait_time` | object | Time spent waiting in queue |

---

## Behavior

When the priority extension is included:

1. Server SHOULD consider priority for scheduling
2. Server MAY downgrade priority based on quotas
3. Server MUST return whether priority was honored
4. Higher priority does NOT guarantee faster completion

### Priority vs Rate Limits

Priority operates independently of rate limits:
- Rate limits apply equally regardless of priority
- Priority affects order within allowed requests
- Servers MAY have separate rate limits per priority level

---

## Examples

### High Priority Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_urgent",
  "call": {
    "function": "payments.process",
    "version": "1.0.0",
    "arguments": {
      "payment_id": "pay_123",
      "amount": 99.99
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:priority",
      "options": {
        "level": "high",
        "reason": "user_checkout"
      }
    }
  ]
}
```

### Priority Honored Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_urgent",
  "result": {
    "payment_id": "pay_123",
    "status": "completed"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:priority",
      "data": {
        "honored": true,
        "effective_level": "high",
        "wait_time": { "value": 5, "unit": "millisecond" }
      }
    }
  ]
}
```

### Low Priority Background Task

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_background",
  "call": {
    "function": "analytics.aggregate",
    "version": "1.0.0",
    "arguments": {
      "date_range": { "start": "2024-01-01", "end": "2024-01-31" }
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:priority",
      "options": {
        "level": "bulk",
        "reason": "scheduled_report"
      }
    }
  ]
}
```

### Priority Downgraded Response

Server may downgrade priority based on client quotas:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_downgraded",
  "result": { "processed": true },
  "extensions": [
    {
      "urn": "urn:forrst:ext:priority",
      "data": {
        "honored": false,
        "effective_level": "normal",
        "wait_time": { "value": 250, "unit": "millisecond" }
      }
    }
  ]
}
```

### Queued Response

When server is under load:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_queued",
  "result": { "report_id": "rpt_456" },
  "extensions": [
    {
      "urn": "urn:forrst:ext:priority",
      "data": {
        "honored": true,
        "effective_level": "low",
        "queue_position": 47,
        "wait_time": { "value": 3, "unit": "second" }
      }
    }
  ]
}
```

---

## Server Implementation

### Queue Strategy

Servers MAY implement priority queues:

```
┌──────────────────────────────────────────┐
│                 Incoming Requests         │
└────────────────────┬─────────────────────┘
                     │
    ┌────────────────┼────────────────┐
    ▼                ▼                ▼
┌────────┐    ┌──────────┐    ┌───────────┐
│Critical│    │  High/   │    │Low/Bulk   │
│ Queue  │    │  Normal  │    │  Queue    │
└───┬────┘    └────┬─────┘    └─────┬─────┘
    │              │                │
    └──────────────┴────────────────┘
                   │
                   ▼
            ┌────────────┐
            │  Workers   │
            └────────────┘
```

### Quota-Based Limits

Servers SHOULD set priority quotas per client:

| Client Tier | Critical | High | Normal | Low/Bulk |
|-------------|----------|------|--------|----------|
| Free        | 0%       | 10%  | 70%    | 20%      |
| Pro         | 5%       | 30%  | 50%    | 15%      |
| Enterprise  | 10%      | 40%  | 40%    | 10%      |
