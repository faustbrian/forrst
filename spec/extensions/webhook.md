---
title: Webhook
description: Standard Webhooks-compliant event notifications with HMAC and Ed25519 signatures
---

# Webhook

> Standard Webhooks-compliant event notifications with HMAC and Ed25519 signatures

**Extension URN:** `urn:cline:forrst:ext:webhook`

---

## Overview

The webhook extension enables Standard Webhooks-compliant HTTP callbacks for event notifications. Functions and extensions can dispatch webhooks to notify external systems of events, with cryptographic signatures for verification.

This extension implements the [Standard Webhooks](https://www.standardwebhooks.com/) specification, providing:
- Pluggable signature strategies (HMAC-SHA256, Ed25519)
- Automatic retry with exponential backoff
- Standardized headers and payload formats
- Response code handling per Standard Webhooks spec

---

## When to Use

Webhooks SHOULD be used for:
- Notifying external systems of async operation completion
- Event-driven integrations with third-party services
- Real-time data synchronization
- Audit trail and logging to external systems
- Triggering workflows in external platforms

Webhooks SHOULD NOT be used for:
- Synchronous request-response patterns (use regular RPC)
- High-frequency events (consider streaming or batching)
- Internal event bus (use framework events)

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `callback_url` | string | Yes | HTTPS URL to receive webhook POST requests |
| `events` | array\<string\> | No | Event types to subscribe to (function-specific) |
| `signature_version` | string | No | Signature algorithm: `v1` (HMAC-SHA256) or `v1a` (Ed25519). Defaults to server configuration. |

---

## Data (Response)

The webhook extension confirms webhook registration:

| Field | Type | Description |
|-------|------|-------------|
| `registered` | boolean | Whether webhook was successfully registered |
| `callback_url` | string | Registered callback URL |
| `events` | array\<string\> | Registered event types |
| `signature_version` | string | Signature algorithm in use |

---

## Behavior

### Registration

1. Client provides `callback_url` in extension options
2. Server validates URL format (HTTPS required)
3. Server stores callback URL with operation/function context
4. Server returns confirmation in response extensions

### Dispatch

When an event occurs:

1. Server generates unique webhook ID (`msg_{ULID}`)
2. Server creates payload with event type and data
3. Server signs payload using configured signature strategy
4. Server dispatches HTTP POST to callback URL with Standard Webhooks headers
5. Server implements retry logic with exponential backoff

### Standard Webhooks Headers

All webhook requests include:

| Header | Description | Example |
|--------|-------------|---------|
| `webhook-id` | Unique identifier for this webhook delivery | `msg_01HQZJX9K2M3N4P5Q6R7S8T9V0` |
| `webhook-timestamp` | Unix timestamp when webhook was sent | `1703520000` |
| `webhook-signature` | Cryptographic signature(s) | `v1,base64signature` |
| `content-type` | Always `application/json` | `application/json` |

### Signature Format

Signatures follow Standard Webhooks format:

```
{version},{base64_signature}
```

**Signing payload:**
```
{webhook_id}.{timestamp}.{body}
```

**Example:**
```
webhook-signature: v1,Cg1tZGZnMTIz...
```

### Response Code Handling

| Status Code | Behavior |
|-------------|----------|
| 2xx | Success, no retry |
| 410 Gone | Endpoint disabled, stop retrying |
| 429 Too Many Requests | Rate limited, slow down retry |
| Other 4xx/5xx | Retry with exponential backoff |

### Retry Logic

Default retry configuration (per Standard Webhooks recommendation):

- Max attempts: 5
- Backoff intervals: 5s, 30s, 2min, 10min, 1hr
- Strategy: Exponential backoff with jitter

---

## Signature Algorithms

### HMAC-SHA256 (v1)

**Version prefix:** `v1`
**Algorithm:** `hash_hmac('sha256', payload, secret)`
**Secret format:** Base64-encoded (24-64 bytes), optional `whsec_` prefix

**Use when:**
- Symmetric signatures are acceptable
- Maximum compatibility required
- Simpler key management preferred

### Ed25519 (v1a)

**Version prefix:** `v1a`
**Algorithm:** `sodium_crypto_sign_detached(payload, secret_key)`
**Secret format:** Base64-encoded (64 bytes), `whsk_` prefix
**Public key format:** Base64-encoded (32 bytes), `whpk_` prefix

**Use when:**
- Asymmetric signatures required
- Higher security standards needed
- Non-repudiation is important

---

## Examples

### Webhook Request (Registration)

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_webhook",
  "call": {
    "function": "processing.start",
    "version": "1.0.0",
    "arguments": {
      "data": "..."
    }
  },
  "extensions": [
    {
      "urn": "urn:cline:forrst:ext:async",
      "options": {
        "accept": true
      }
    },
    {
      "urn": "urn:cline:forrst:ext:webhook",
      "options": {
        "callback_url": "https://example.com/webhooks/operations",
        "events": ["operation.completed", "operation.failed"],
        "signature_version": "v1"
      }
    }
  ]
}
```

### Webhook Response (Confirmation)

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_webhook",
  "result": {
    "operation_id": "op_01HQZJX9K2M3N4P5Q6R7S8T9V0",
    "status": "pending"
  },
  "extensions": [
    {
      "urn": "urn:cline:forrst:ext:async",
      "data": {
        "operation_id": "op_01HQZJX9K2M3N4P5Q6R7S8T9V0",
        "status": "pending"
      }
    },
    {
      "urn": "urn:cline:forrst:ext:webhook",
      "data": {
        "registered": true,
        "callback_url": "https://example.com/webhooks/operations",
        "events": ["operation.completed", "operation.failed"],
        "signature_version": "v1"
      }
    }
  ]
}
```

### Webhook Delivery (HTTP POST)

**Request:**
```http
POST /webhooks/operations HTTP/1.1
Host: example.com
Content-Type: application/json
webhook-id: msg_01HQZK1234567890ABCDEFGHIJ
webhook-timestamp: 1703520000
webhook-signature: v1,vMHHvL6LN9L+IQKNXERGzq0Qz...

{
  "type": "forrst.operation.completed",
  "timestamp": "2024-01-01T00:00:00Z",
  "data": {
    "operation_id": "op_01HQZJX9K2M3N4P5Q6R7S8T9V0",
    "function": "processing.start",
    "version": "1.0.0",
    "status": "completed",
    "result": {
      "success": true,
      "data": "..."
    },
    "completed_at": "2024-01-01T00:00:00Z"
  }
}
```

**Response:**
```http
HTTP/1.1 200 OK
Content-Type: application/json

{
  "received": true
}
```

---

## Event Types

### Standard Events

Extensions and functions define their own event types. Common patterns:

| Event Type | Description |
|------------|-------------|
| `forrst.operation.completed` | Async operation finished successfully |
| `forrst.operation.failed` | Async operation failed |
| `forrst.operation.cancelled` | Async operation was cancelled |
| `{namespace}.{resource}.{action}` | Custom event pattern |

---

## Security Considerations

### Signature Verification

Recipients MUST verify webhook signatures:

1. Extract signature from `webhook-signature` header
2. Reconstruct signing payload: `{webhook-id}.{timestamp}.{body}`
3. Verify signature using configured algorithm and secret
4. Use constant-time comparison to prevent timing attacks

### Timestamp Validation

Recipients SHOULD validate timestamps to prevent replay attacks:

1. Extract timestamp from `webhook-timestamp` header
2. Compare to current time
3. Reject if outside acceptable tolerance (recommended: ±5 minutes)

### HTTPS Required

Callback URLs MUST use HTTPS. HTTP URLs MUST be rejected.

### Secret Management

- Secrets SHOULD be rotated periodically
- Secrets MUST be stored securely (environment variables, secret managers)
- Secrets MUST NOT be logged or exposed in error messages

---

## Configuration

Server-side configuration (example):

```php
// config/webhook.php
return [
    'enabled' => env('WEBHOOK_ENABLED', true),
    'signature_version' => env('WEBHOOK_SIGNATURE', 'v1'), // 'v1' or 'v1a'
    'secret' => env('WEBHOOK_SECRET'),
    'timeout' => env('WEBHOOK_TIMEOUT', 5), // seconds
    'retry' => [
        'max_attempts' => 5,
        'backoff' => [5, 30, 120, 600, 3600], // seconds
    ],
];
```

---

## Integration with Other Extensions

### Async Extension

The webhook extension is commonly used with the async extension:

```json
{
  "extensions": [
    {
      "urn": "urn:cline:forrst:ext:async",
      "options": { "accept": true }
    },
    {
      "urn": "urn:cline:forrst:ext:webhook",
      "options": {
        "callback_url": "https://example.com/webhooks",
        "events": ["operation.completed", "operation.failed"]
      }
    }
  ]
}
```

When an async operation completes, the server dispatches a webhook to the callback URL.

### Other Extensions

Any extension can leverage the webhook extension for notifications:

- **Replay**: Notify when replay completes
- **Simulation**: Notify of simulation results
- **Query**: Stream large query results via webhooks
- **Maintenance**: Notify when maintenance windows occur

---

## Implementation Notes

### URL Validation

Servers MUST validate callback URLs:
- Protocol must be `https://`
- URL must be well-formed
- Domain must be resolvable (optional)
- Port restrictions may apply (optional)

### Idempotency

Webhook deliveries are idempotent when possible:
- Same `webhook-id` for retries
- Recipients can deduplicate using `webhook-id`

### Monitoring

Servers SHOULD track:
- Webhook delivery success/failure rates
- Response times
- Retry attempts
- Endpoint health (410 Gone responses)

### Rate Limiting

Servers MAY implement rate limiting:
- Per-endpoint quotas
- Global webhook quotas
- Backpressure on 429 responses

---

## Related Specifications

- [Standard Webhooks](https://www.standardwebhooks.com/)
- [RFC 3986: Uniform Resource Identifier (URI)](https://www.rfc-editor.org/rfc/rfc3986)
- [RFC 6749: OAuth 2.0](https://www.rfc-editor.org/rfc/rfc6749) (for secured endpoints)

---

## FAQ

**Q: Can I use multiple signature versions simultaneously?**
A: The Standard Webhooks spec supports multiple signatures in the header, but this implementation uses a single configured version per server.

**Q: What happens if the callback URL is unreachable?**
A: The webhook is retried according to the retry configuration. After all attempts fail, the webhook is marked as failed and logged.

**Q: Can I receive webhooks for specific events only?**
A: Yes, use the `events` field in options to filter event types.

**Q: How do I test webhook integrations?**
A: Use services like webhook.site, ngrok, or local tunneling for development testing.

**Q: Are webhooks guaranteed to be delivered in order?**
A: No. Webhooks may be delivered out of order due to retries and network delays. Use timestamps and event sequencing in your application logic.
