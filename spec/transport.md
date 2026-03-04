---
title: Transport Bindings
description: Protocol mapping for HTTP and message queues
---

# Transport Bindings

> Protocol mapping for HTTP and message queues

---

## Overview

Forrst is transport-agnostic. This document specifies conventions for common transports to ensure interoperability.

---

## HTTP

HTTP bindings follow [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110) (HTTP Semantics) for status codes, methods, and headers.

### Request

| Aspect | Convention |
|--------|------------|
| Method | `POST` |
| Content-Type | `application/json` |
| Endpoint | Service-defined (e.g., `/forrst`, `/rpc`, `/api`) |
| Body | JSON-encoded Forrst request |

### Response

| Aspect | Convention |
|--------|------------|
| Content-Type | `application/json` |
| Status Code | Semantic HTTP status code matching error |
| Body | JSON-encoded Forrst response |

### Status Codes

HTTP status codes MUST reflect the Forrst error. Success responses return `200 OK`; error responses return the appropriate HTTP status code:

```http
HTTP/1.1 404 Not Found
Content-Type: application/json

{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [{
    "code": "NOT_FOUND",
    "message": "Order not found",
  }]
}
```

See [Errors](errors.md) for the complete mapping of error codes to HTTP status codes.

**Common status codes:**

| Status | Error Codes |
|--------|-------------|
| `200 OK` | Success (no errors) |
| `400 Bad Request` | `PARSE_ERROR`, `INVALID_REQUEST`, `INVALID_ARGUMENTS` |
| `401 Unauthorized` | `UNAUTHORIZED` |
| `403 Forbidden` | `FORBIDDEN` |
| `404 Not Found` | `NOT_FOUND`, `FUNCTION_NOT_FOUND` |
| `429 Too Many Requests` | `RATE_LIMITED` |
| `500 Internal Server Error` | `INTERNAL_ERROR` |
| `502 Bad Gateway` | `DEPENDENCY_ERROR` |
| `503 Service Unavailable` | `UNAVAILABLE`, `SERVER_MAINTENANCE`, `FUNCTION_MAINTENANCE` |

**Multiple Errors:**

When a response contains multiple errors with potentially different status codes, the HTTP status code MUST be `400 Bad Request`. This provides a consistent, generic client error status while the response body's `errors` array contains the specific error details with individual error codes.

Example:
```http
HTTP/1.1 400 Bad Request
Content-Type: application/json

{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_456",
  "result": null,
  "errors": [
    {
      "code": "INVALID_ARGUMENTS",
      "message": "Email format is invalid",
      "source": { "pointer": "/call/arguments/email" }
    },
    {
      "code": "INVALID_ARGUMENTS",
      "message": "Quantity must be positive",
      "source": { "pointer": "/call/arguments/quantity" }
    }
  ]
}
```

---

### Header Mapping

HTTP headers provide metadata for infrastructure components (load balancers, proxies, observability tools) without parsing the JSON body.

#### Request Headers

| Header | Maps To | Description |
|--------|---------|-------------|
| `X-Forrst-Request-Id` | `id` | Request identifier |
| `X-Forrst-Trace-Id` | tracing extension | Distributed trace ID |
| `X-Forrst-Span-Id` | tracing extension | Current span ID |
| `X-Forrst-Parent-Span-Id` | tracing extension | Parent span ID |
| `X-Forrst-Caller` | `context.caller` | Calling service name |

Trace headers map to the [Tracing Extension](extensions/tracing.md), not context.

#### Response Headers

| Header | Maps To | Description |
|--------|---------|-------------|
| `X-Forrst-Request-Id` | `id` | Echoed request identifier |
| `X-Forrst-Duration-Ms` | `meta.duration` | Processing time in milliseconds |
| `X-Forrst-Node` | `meta.node` | Handling node identifier |
| `RateLimit-Limit` | `meta.rate_limit.limit` | Rate limit ceiling |
| `RateLimit-Remaining` | `meta.rate_limit.remaining` | Remaining requests |
| `RateLimit-Reset` | `meta.rate_limit.resets_in` | Seconds until window reset |
| `RateLimit-Policy` | — | Policy identifier (optional) |

Rate limit headers follow [IETF draft-ietf-httpapi-ratelimit-headers](https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/).

#### Rules

- Headers are OPTIONAL — the JSON body is authoritative
- Servers SHOULD set headers for observability
- Clients SHOULD set trace headers for propagation
- When headers and body conflict, body takes precedence

**Format differences:** Headers use string representations (milliseconds for duration). The JSON body uses structured objects. Servers MUST convert between formats when mapping headers to body.

#### Example

```http
POST /forrst HTTP/1.1
Host: orders-api.internal
Content-Type: application/json
X-Forrst-Request-Id: req_xyz789
X-Forrst-Trace-Id: tr_8f3a2b1c
X-Forrst-Span-Id: sp_4d5e6f
X-Forrst-Caller: checkout-service

{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_xyz789",
  "call": {
    "function": "orders.create",
    "version": "1.0.0",
    "arguments": { "customer_id": 42 }
  },
  "context": {
    "caller": "checkout-service"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:deadline",
      "options": { "value": 5, "unit": "second" }
    },
    {
      "urn": "urn:forrst:ext:tracing",
      "options": {
        "trace_id": "tr_8f3a2b1c",
        "span_id": "sp_4d5e6f"
      }
    }
  ]
}
```

```http
HTTP/1.1 200 OK
Content-Type: application/json
X-Forrst-Request-Id: req_xyz789
X-Forrst-Duration-Ms: 127
X-Forrst-Node: orders-api-2
RateLimit-Limit: 1000
RateLimit-Remaining: 847
RateLimit-Reset: 45

{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_xyz789",
  "result": { "order_id": 12345 },
  "extensions": [
    {
      "urn": "urn:forrst:ext:tracing",
      "data": {
        "trace_id": "tr_abc123",
        "span_id": "span_server_001",
        "duration": { "value": 127, "unit": "millisecond" }
      }
    }
  ],
  "meta": {
    "node": "orders-api-2",
    "rate_limit": {
      "limit": 1000,
      "remaining": 847,
      "resets_in": { "value": 45, "unit": "second" }
    }
  }
}
```

---

### Timeouts

HTTP clients SHOULD configure connection and read timeouts:

| Timeout | Recommendation |
|---------|----------------|
| Connection | 5 seconds |
| Read | Forrst deadline + buffer (e.g., deadline + 1s) |

The read timeout SHOULD exceed the Forrst deadline to allow the server to return a `DEADLINE_EXCEEDED` error rather than the connection being cut.

---

### Keep-Alive

HTTP/1.1 connections SHOULD use keep-alive for efficiency. HTTP/2 is RECOMMENDED for high-throughput scenarios as it provides:

- Connection multiplexing
- Header compression
- Reduced latency

---

## Message Queues

### Overview

Message queues enable asynchronous request processing. Common implementations: RabbitMQ, Redis Streams, Amazon SQS.

### Message Structure

| Aspect | Convention |
|--------|------------|
| Body | JSON-encoded Forrst request |
| Content-Type | `application/json` |
| Correlation ID | Forrst `id` field |
| Reply-To | Queue for response (if expecting reply) |

### Property Mapping

#### Request Message Properties

| Property | Maps To | Description |
|----------|---------|-------------|
| `correlation_id` | `id` | Request identifier |
| `reply_to` | — | Response queue name |
| `expiration` | deadline extension | Message TTL in milliseconds |
| `headers.trace_id` | tracing extension | Distributed trace ID |
| `headers.span_id` | tracing extension | Current span ID |
| `headers.caller` | `context.caller` | Calling service |

Trace headers map to the [Tracing Extension](extensions/tracing.md), not context.

#### Response Message Properties

| Property | Maps To | Description |
|----------|---------|-------------|
| `correlation_id` | `id` | Echoed request identifier |
| `headers.duration_ms` | `meta.duration` | Processing time |
| `headers.node` | `meta.node` | Handling node |

### Deadline Handling

Message TTL SHOULD reflect the Forrst deadline:

```
message_ttl = deadline_value (converted to ms)
```

When a message expires before processing:
- Queue MAY move it to dead letter queue
- Consumer SHOULD NOT process expired messages
- If processed, server SHOULD return `DEADLINE_EXCEEDED`

### Request-Response Pattern

For standard requests expecting responses:

1. Client publishes request to service queue
2. Client waits on reply queue (from `reply_to`)
3. Server processes request
4. Server publishes response to reply queue
5. Client receives response, matched by `correlation_id`

```
┌──────────┐     request      ┌───────────────┐
│  Client  │ ───────────────► │ Service Queue │
└──────────┘                  └───────────────┘
     ▲                              │
     │                              ▼
     │                        ┌───────────┐
     │         response       │  Server   │
     └─────────────────────── │           │
           (reply queue)      └───────────┘
```

### Dead Letter Handling

Failed messages SHOULD be routed to a dead letter queue when:

- Message expires (TTL exceeded)
- Processing fails after max retries
- Message cannot be parsed

Dead letter queues enable:
- Debugging failed requests
- Manual retry/reprocessing
- Alerting on failure patterns

### Example: RabbitMQ

**Publishing Request:**

```php
$channel->basic_publish(
    new AMQPMessage(
        json_encode($forrstRequest),
        [
            'content_type' => 'application/json',
            'correlation_id' => $forrstRequest['id'],
            'reply_to' => 'responses.checkout-service',
            'expiration' => '5000', // 5 seconds
            'headers' => new AMQPTable([
                'trace_id' => $tracingExtension['options']['trace_id'] ?? null,
                'span_id' => $tracingExtension['options']['span_id'] ?? null,
                'caller' => 'checkout-service',
            ]),
        ]
    ),
    '',
    'orders-api'
);
```

**Publishing Response:**

```php
$channel->basic_publish(
    new AMQPMessage(
        json_encode($forrstResponse),
        [
            'content_type' => 'application/json',
            'correlation_id' => $forrstResponse['id'],
            'headers' => new AMQPTable([
                'duration_ms' => 127,
                'node' => 'orders-api-2',
            ]),
        ]
    ),
    '',
    $replyTo
);
```

---

## Unix Sockets

For local inter-process communication:

| Aspect | Convention |
|--------|------------|
| Protocol | Stream socket |
| Framing | Length-prefixed JSON |
| Path | Service-defined (e.g., `/var/run/forrst/orders.sock`) |

### Message Framing

Each message is prefixed with a 4-byte big-endian length:

```
┌──────────────┬─────────────────────┐
│ Length (4B)  │ JSON Payload        │
└──────────────┴─────────────────────┘
```

This allows efficient message boundary detection without delimiter scanning.

---

## Implementation Notes

### Content Negotiation

Forrst uses JSON exclusively. Servers SHOULD reject requests with unsupported content types:

```http
HTTP/1.1 415 Unsupported Media Type
Content-Type: application/json

{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": null,
  "result": null,
  "errors": [{
    "code": "INVALID_REQUEST",
    "message": "Content-Type must be application/json",
  }]
}
```

### Request Size Limits

Servers SHOULD enforce request size limits:

| Limit | Recommendation |
|-------|----------------|
| Maximum request body | 1 MB |
| Maximum header size | 8 KB |

Exceeded limits return `413 Payload Too Large` (HTTP) or reject the message (queues).

### Compression

Compression is handled at the transport layer:

- **HTTP:** Use standard `Accept-Encoding`/`Content-Encoding` headers (gzip, br)
- **Queues:** Configure at broker level or use compressed message body

Forrst does not specify compression — it is an infrastructure concern.

---

## Authentication

Authentication is an implementation concern handled at the transport layer. Forrst does not specify authentication mechanisms but provides guidance for common patterns.

### HTTP Authentication

#### Bearer Tokens

Use standard `Authorization` header for token-based authentication per [RFC 6750](https://www.rfc-editor.org/rfc/rfc6750) (OAuth 2.0 Bearer Token Usage):

```http
POST /forrst HTTP/1.1
Host: orders-api.internal
Content-Type: application/json
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

Servers validate the token before processing the Forrst request. Invalid tokens return transport-level errors:

```http
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Bearer realm="forrst-api"

{
  "error": "invalid_token",
  "error_description": "Token has expired"
}
```

Note: This is a transport-level error, not a Forrst error. The request never reached the Forrst handler.

#### API Keys

For service-to-service authentication:

```http
POST /forrst HTTP/1.1
Host: orders-api.internal
Content-Type: application/json
X-API-Key: sk_live_abc123xyz
```

#### Mutual TLS (mTLS)

For internal service authentication, use mutual TLS per [RFC 8446](https://www.rfc-editor.org/rfc/rfc8446) (TLS 1.3) at the transport layer. The client presents a certificate that identifies the calling service:

```
┌─────────────┐      mTLS      ┌─────────────┐
│  Service A  │ ◄────────────► │  Service B  │
│ (client)    │   cert: A      │ (server)    │
└─────────────┘                └─────────────┘
```

With mTLS, the server can populate `context.caller` from the client certificate's Common Name (CN) or Subject Alternative Name (SAN).

### Message Queue Authentication

For message queues, authentication typically occurs at connection time:

- **RabbitMQ:** Username/password or x509 certificates
- **Amazon SQS:** IAM roles and policies
- **Redis Streams:** ACL authentication

The queue broker validates credentials before allowing message publication or consumption.

### Authorization Context

After transport-level authentication, authorization data should be propagated via context:

```json
{
  "context": {
    "caller": "checkout-service",
    "tenant_id": "tenant_acme",
    "user_id": "user_42",
    "scopes": ["orders:read", "orders:write"]
  }
}
```

This enables:
- Multi-tenant isolation (filter by `tenant_id`)
- User-level authorization (validate `user_id` has access)
- Scope-based access control (check `scopes` for permission)

### Internal vs External APIs

| Aspect | Internal (service-to-service) | External (client-facing) |
|--------|------------------------------|--------------------------|
| Authentication | mTLS, API keys | OAuth 2.0, JWT |
| Trust level | High (verified service) | Low (validate everything) |
| Rate limiting | Per-service quotas | Per-user/tenant quotas |
| Context source | Propagated from upstream | Extracted from token |

### Security Recommendations

1. **Always use TLS** — Never send Forrst requests over unencrypted connections
2. **Validate at the edge** — Authenticate external requests at API gateway
3. **Propagate identity** — Pass authenticated identity via context
4. **Principle of least privilege** — Scope service permissions narrowly
5. **Rotate credentials** — Use short-lived tokens where possible
