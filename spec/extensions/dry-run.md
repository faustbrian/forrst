---
title: Dry Run
description: Validate operations without executing
---

# Dry Run

> Validate operations without executing

**Extension URN:** `urn:forrst:ext:dry-run`

---

## Overview

The dry-run extension validates mutations without executing them. Useful for previewing changes, validating complex inputs, and implementing confirmation flows.

---

## When to Use

Dry-run SHOULD be used for:
- Destructive operations requiring confirmation
- Complex mutations with multiple side effects
- Cost estimation before execution
- Input validation before submission
- "What-if" analysis

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | Yes | Enable dry-run mode |
| `include_diff` | boolean | No | Include before/after comparison |
| `include_side_effects` | boolean | No | List operations that would occur |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `valid` | boolean | Whether operation would succeed |
| `would_affect` | array | Resources that would be modified |
| `diff` | object | Before/after state comparison |
| `side_effects` | array | Operations that would be triggered |
| `validation_errors` | array | Issues that would prevent execution |
| `estimated_duration` | object | Estimated execution time |

---

## Behavior

When the dry-run extension is enabled:

1. Server MUST validate all inputs
2. Server MUST NOT modify any state
3. Server MUST NOT trigger side effects (webhooks, notifications, etc.)
4. Server MUST return what would happen if executed
5. Server SHOULD return estimated impact

---

## Examples

### Dry-Run Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_dry",
  "call": {
    "function": "orders.bulk_cancel",
    "version": "1.0.0",
    "arguments": {
      "filter": {
        "status": "pending",
        "created_before": "2024-01-01"
      }
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:dry-run",
      "options": {
        "enabled": true,
        "include_diff": true,
        "include_side_effects": true
      }
    }
  ]
}
```

### Successful Dry-Run Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_dry",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:dry-run",
      "data": {
        "valid": true,
        "would_affect": [
          { "type": "order", "id": "ord_001", "action": "cancel" },
          { "type": "order", "id": "ord_002", "action": "cancel" },
          { "type": "order", "id": "ord_003", "action": "cancel" }
        ],
        "diff": {
          "orders": {
            "before": { "pending": 47, "cancelled": 12 },
            "after": { "pending": 44, "cancelled": 15 }
          }
        },
        "side_effects": [
          { "type": "webhook", "event": "order.cancelled", "count": 3 },
          { "type": "email", "template": "order_cancelled", "count": 3 },
          { "type": "refund", "total": { "amount": 450.00, "currency": "USD" } }
        ],
        "estimated_duration": { "value": 2, "unit": "second" }
      }
    }
  ]
}
```

### Invalid Dry-Run Response

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_dry_invalid",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:dry-run",
      "data": {
        "valid": false,
        "validation_errors": [
          {
            "field": "filter.created_before",
            "code": "INVALID_DATE_FORMAT",
            "message": "Expected ISO 8601 date format"
          },
          {
            "field": "filter.status",
            "code": "INVALID_ENUM",
            "message": "Status 'pendng' not recognized. Did you mean 'pending'?"
          }
        ]
      }
    }
  ]
}
```

### Delete Preview

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_delete_preview",
  "call": {
    "function": "users.delete",
    "version": "1.0.0",
    "arguments": { "user_id": 42 }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:dry-run",
      "options": {
        "enabled": true,
        "include_side_effects": true
      }
    }
  ]
}
```

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_delete_preview",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:dry-run",
      "data": {
        "valid": true,
        "would_affect": [
          { "type": "user", "id": 42, "action": "delete" },
          { "type": "order", "count": 15, "action": "anonymize" },
          { "type": "review", "count": 8, "action": "delete" },
          { "type": "session", "count": 3, "action": "invalidate" }
        ],
        "side_effects": [
          { "type": "email", "template": "account_deleted", "count": 1 },
          { "type": "webhook", "event": "user.deleted", "count": 1 },
          { "type": "audit_log", "action": "user_deletion", "count": 1 }
        ]
      }
    }
  ]
}
```

---

## Notes

- Dry-run responses SHOULD be deterministic for the same input
- Complex operations MAY return approximations for `would_affect` counts
- Servers MAY cache dry-run results for a short period
