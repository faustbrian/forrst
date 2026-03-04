---
title: Retry
description: Structured retry guidance for failed requests
---

# Retry

> Structured retry guidance for failed requests

**Extension URN:** `urn:forrst:ext:retry`

---

## Overview

The retry extension provides structured retry information for failed requests. It replaces ad-hoc retry logic with explicit guidance from the server, enabling clients to implement consistent and appropriate retry behavior.

---

## When to Use

The retry extension is automatically included when:
- A request fails with a retryable error code
- The server wants to provide specific retry timing

The retry extension is NOT included when:
- The request succeeds
- The error is definitively non-retryable

---

## Options (Request)

This extension does not accept request options. It is a response-only extension.

---

## Data (Response)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `allowed` | boolean | Yes | Whether retry is permitted |
| `strategy` | string | No | Retry strategy (see below) |
| `after` | object | No | Minimum wait before retry |
| `max_attempts` | integer | No | Suggested maximum retry attempts |

### After Object

| Field | Type | Description |
|-------|------|-------------|
| `value` | integer | Duration value |
| `unit` | string | Duration unit (`second`, `minute`) |

### Retry Strategies

| Strategy | Description |
|----------|-------------|
| `immediate` | Retry immediately without delay |
| `fixed` | Wait fixed duration between retries |
| `exponential` | Double wait time between retries |

---

## Behavior

### Automatic Retry Guidance

The server automatically adds retry guidance based on error codes:

| Error Code | Strategy | Default After | Max Attempts |
|------------|----------|---------------|--------------|
| `RATE_LIMITED` | fixed | 60s | 3 |
| `UNAVAILABLE` | exponential | 1s | 5 |
| `DEADLINE_EXCEEDED` | immediate | 0s | 1 |
| `INTERNAL_ERROR` | exponential | 1s | 3 |
| `DEPENDENCY_ERROR` | exponential | 2s | 3 |
| `IDEMPOTENCY_PROCESSING` | fixed | 1s | 3 |
| `SERVER_MAINTENANCE` | fixed | 60s | 1 |
| `FUNCTION_MAINTENANCE` | fixed | 60s | 1 |
| `FUNCTION_DISABLED` | fixed | 30s | 2 |

### Non-Retryable Errors

For non-retryable errors, the extension returns:

```json
{
  "urn": "urn:forrst:ext:retry",
  "data": {
    "allowed": false
  }
}
```

Non-retryable error codes include:
- `INVALID_ARGUMENTS`
- `NOT_FOUND`
- `UNAUTHORIZED`
- `FORBIDDEN`
- `CANCELLED`
- `VALIDATION_ERROR`

---

## Examples

### Retryable Error with Exponential Backoff

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [{
    "code": "UNAVAILABLE",
    "message": "Service temporarily unavailable",
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:retry",
      "data": {
        "allowed": true,
        "strategy": "exponential",
        "after": { "value": 1, "unit": "second" },
        "max_attempts": 5
      }
    }
  ]
}
```

### Rate Limited with Fixed Delay

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_456",
  "result": null,
  "errors": [{
    "code": "RATE_LIMITED",
    "message": "Rate limit exceeded",
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:retry",
      "data": {
        "allowed": true,
        "strategy": "fixed",
        "after": { "value": 60, "unit": "second" },
        "max_attempts": 3
      }
    }
  ]
}
```

### Immediate Retry Allowed

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_789",
  "result": null,
  "errors": [{
    "code": "DEADLINE_EXCEEDED",
    "message": "Request deadline exceeded",
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:retry",
      "data": {
        "allowed": true,
        "strategy": "immediate",
        "max_attempts": 1
      }
    }
  ]
}
```

### Non-Retryable Error

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_error",
  "result": null,
  "errors": [{
    "code": "INVALID_ARGUMENTS",
    "message": "Invalid email format",
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:retry",
      "data": {
        "allowed": false
      }
    }
  ]
}
```

---

## Client Implementation

### Retry Loop Example

```javascript
async function callWithRetry(request, maxAttempts = 3) {
  let attempt = 0;
  let delay = 0;

  while (attempt < maxAttempts) {
    attempt++;

    if (delay > 0) {
      await sleep(delay);
    }

    const response = await forrstCall(request);

    if (!response.errors) {
      return response;
    }

    const retryExt = response.extensions?.find(
      e => e.urn === 'urn:forrst:ext:retry'
    );

    if (!retryExt?.data?.allowed) {
      throw new Error(response.errors[0].message);
    }

    const { strategy, after, max_attempts } = retryExt.data;

    if (attempt >= (max_attempts || maxAttempts)) {
      throw new Error('Max retry attempts exceeded');
    }

    // Calculate delay
    const baseDelay = after?.value || 1;
    switch (strategy) {
      case 'immediate':
        delay = 0;
        break;
      case 'fixed':
        delay = baseDelay * 1000;
        break;
      case 'exponential':
        delay = baseDelay * Math.pow(2, attempt - 1) * 1000;
        break;
    }
  }
}
```

---

## Integration with Other Extensions

### With Idempotency

When retrying with idempotency:

```json
{
  "extensions": [
    {
      "urn": "urn:forrst:ext:idempotency",
      "options": { "key": "order_123" }
    }
  ]
}
```

The retry extension respects idempotency—retried requests with the same key return cached results.

### With Rate Limit

The rate limit extension provides more granular timing:

```json
{
  "extensions": [
    {
      "urn": "urn:forrst:ext:rate-limit",
      "data": {
        "limit": 100,
        "remaining": 0,
        "reset": { "value": 45, "unit": "second" }
      }
    },
    {
      "urn": "urn:forrst:ext:retry",
      "data": {
        "allowed": true,
        "strategy": "fixed",
        "after": { "value": 45, "unit": "second" }
      }
    }
  ]
}
```

---

## Server Customization

Servers MAY provide custom retry guidance:

```php
RetryExtension::buildRetryData(
    allowed: true,
    strategy: 'exponential',
    afterValue: 5,
    afterUnit: 'second',
    maxAttempts: 10,
);
```
