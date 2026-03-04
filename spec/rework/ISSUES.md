---
title: "Forrst Protocol: Rework Decisions"
---

# Forrst Protocol: Rework Decisions

> Final verdicts on protocol design issues

**Status:** ✅ All decisions finalized

---

## Final Decisions

### 1. HTTP Status Codes — ✅ FINAL: Semantic Mapping

**Decision:** Use proper HTTP status codes that reflect the error type.

| Forrst Error | HTTP Status |
|------------|-------------|
| Success | 200 |
| `INVALID_ARGUMENTS` | 400 |
| `UNAUTHORIZED` | 401 |
| `FORBIDDEN` | 403 |
| `NOT_FOUND` | 404 |
| `CONFLICT` | 409 |
| `RATE_LIMITED` | 429 |
| `INTERNAL_ERROR` | 500 |
| `UNAVAILABLE` | 503 |

**Rationale:** When you choose HTTP transport, speak HTTP. The `errors` array remains authoritative, but status codes enable infrastructure (load balancers, monitoring, CDNs) to work correctly.

With batch removed, mapping is trivially simple: one request → one status.

---

### 2. Batch — ✅ FINAL: Remove Entirely

**Decision:** Remove the batch extension. Forrst is a single-request protocol.

**Rationale:**
- HTTP/2 multiplexing handles concurrent requests
- Atomic transactions belong in domain functions (`ledger.transfer`), not protocol
- Bulk operations belong in domain functions (`users.bulk_create`)
- Client-side orchestration (Laravel Bus::batch) provides what protocols can't:
  - Progress callbacks
  - Partial failure policies
  - Chaining and nesting
  - Retry logic

**Actions:**
- Delete `extensions/batch.md`
- Update FAQ to explain the philosophy
- Remove batch error codes from `errors.md`

---

### 3. Retryable — ✅ FINAL: Move to Extension

**Decision:** Remove `retryable: boolean` from error objects. Create `urn:forrst:ext:retry` extension.

**Rationale:** Different errors need different retry strategies. A boolean can't express timing, backoff, or max attempts.

**Actions:**
- Remove `retryable` field from error schema
- Create retry extension with full semantics
- Deprecation path for existing implementations

---

### 4. Protocol Envelope — ✅ FINAL: Keep, Document Extensibility

**Decision:** Keep the object format. Document that it's an extension point.

```json
{
  "protocol": {
    "name": "forrst",
    "version": "0.1.0",
    "stability": "beta",        // Extension-added
    "features": ["streaming"]   // Extension-added
  }
}
```

**Actions:**
- Add "Protocol Object Extensibility" section to protocol.md
- Document valid extension properties
- Explain the design rationale

---

### 5. Version Format — ✅ FINAL: Semantic Versioning

**Decision:** Function versions use semver format.

```json
{ "version": "1.0.0" }
{ "version": "2.1.0" }
{ "version": "3.0.0-beta.1" }
```

**Rationale:** Industry standard. Clear breaking change signals. Patch-level fixes without version churn.

**Actions:**
- Update versioning.md with semver spec
- Add migration guidance (integer strings → semver)
- Document version resolution rules

---

### 6. Context — ✅ FINAL: Define Standard Fields

**Decision:** Define standard context fields with RECOMMENDED status.

| Field | Type | Status | Description |
|-------|------|--------|-------------|
| `caller` | string | RECOMMENDED | Calling service identifier |
| `request_id` | string | RECOMMENDED | Correlation ID |
| `tenant_id` | string | OPTIONAL | Multi-tenant identifier |
| `user_id` | string | OPTIONAL | End-user identifier |
| `roles` | string[] | OPTIONAL | Authorization roles |

**Actions:**
- Update context.md with standard fields
- Document custom field namespacing
- Add propagation rules

---

### 7. Cancellation — ✅ FINAL: Add Extension

**Decision:** Add `urn:forrst:ext:cancellation` extension for sync request cancellation.

**Actions:**
- Create cancellation extension spec
- Add `CANCELLED` error code
- Document cancellation token pattern

---

### 8. Streaming — ✅ FINAL: SSE with Reserved Extension

**Decision:** Use Server-Sent Events for streaming. Reserve `urn:forrst:ext:stream` URN.

**Implementation:** Simple SSE with standard PHP:

```php
return response()->stream(function () use ($operation) {
    foreach ($operation->chunks() as $chunk) {
        echo "data: " . json_encode([
            'seq' => $chunk->sequence,
            'data' => $chunk->content,
            'done' => $chunk->isLast,
        ]) . "\n\n";
        ob_flush();
        flush();
    }
}, 200, [
    'Content-Type' => 'text/event-stream',
    'Cache-Control' => 'no-cache',
    'X-Accel-Buffering' => 'no',
]);
```

**Actions:**
- Reserve extension URN
- Add interim guidance for SSE usage
- Specify full extension later

---

## Implementation Priority

| Priority | Issue | Effort |
|----------|-------|--------|
| 1 | Remove batch extension | Low |
| 2 | Semantic HTTP status codes | Low |
| 3 | Semantic versioning | Medium |
| 4 | Document protocol extensibility | Low |
| 5 | Context standard fields | Medium |
| 6 | Retry extension | Medium |
| 7 | Cancellation extension | Medium |
| 8 | Streaming extension | High |

---

## Individual Issue Documents

Each design concern has a dedicated document with problem analysis and proposed solutions:

1. [HTTP Status Codes](./01-http-status-codes.md)
2. [Batch Contradiction](./02-batch-contradiction.md)
3. [Retryable Semantics](./03-retryable-semantics.md)
4. [Protocol Envelope](./04-protocol-envelope.md)
5. [Version Format](./05-version-format.md)
6. [Context Specification](./06-context-specification.md)
7. [Request Cancellation](./07-request-cancellation.md)
8. [Streaming Support](./08-streaming-support.md)
