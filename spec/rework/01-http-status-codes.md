---
title: "HTTP Status Codes"
description: "Semantic HTTP status code decisions for the Forrst API"
---

# Issue 1: HTTP Status Codes

> ✅ **FINAL DECISION:** Use semantic HTTP status codes

---

## Decision

**Use proper HTTP status codes that reflect the error type.**

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
| `DEADLINE_EXCEEDED` | 504 |

**The `errors` array remains authoritative.** Clients MUST check the body, not just HTTP status.

With batch removed (see Issue 2), the mapping is simple: one request → one status.

---

## Original Problem

The current spec mandates HTTP 200 for all Forrst responses, with errors expressed only in the `errors` field. The FAQ justifies this as "transport-agnostic."

### Why This Breaks Things

1. **Infrastructure Blindness**
   - Load balancers can't route based on error rates
   - Monitoring tools (Datadog, New Relic) can't detect error spikes
   - CDNs may cache error responses (200 = cacheable by default)
   - Alerting on 5xx rates becomes impossible

2. **Proxy Ambiguity**
   - `429` from a rate-limiting proxy vs `RATE_LIMITED` from Forrst look identical (both HTTP 200)
   - `401` from an auth proxy vs `UNAUTHORIZED` from Forrst are indistinguishable
   - Operators can't tell where errors originate

3. **Caching Hazards**
   - HTTP 200 responses are cacheable by default
   - Error responses could be cached and served to other clients
   - Must add explicit `Cache-Control: no-store` to every response

4. **Debugging Difficulty**
   - `curl -f` won't fail on errors
   - Browser devtools show green for failed requests
   - Log aggregation can't filter by status code

### Current Spec

```json
// Error response (HTTP 200)
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_123",
  "result": null,
  "errors": [{ "code": "NOT_FOUND", ... }]
}
```

---

## Batch Complication

**Key Question:** If batch has 10 operations where 8 pass (200) and 2 fail (429), what HTTP status?

This is the crux of the HTTP status problem with batch operations.

### Options for Batch Status

| Strategy | HTTP Status | Rationale |
|----------|-------------|-----------|
| **207 Multi-Status** | 207 | WebDAV standard for mixed results |
| **Highest Severity** | 429 | If any 5xx → 5xx, else if any 4xx → 4xx |
| **Success if Any** | 200 | Partial success = success |
| **Always 200** | 200 | Current behavior, body has details |

**207 Multi-Status** is designed for exactly this scenario (WebDAV RFC 4918):

```http
HTTP/1.1 207 Multi-Status
Content-Type: application/json

{
  "extensions": [{
    "urn": "urn:forrst:ext:batch",
    "data": {
      "results": [
        { "id": "op1", "status": 200, "result": {...} },
        { "id": "op2", "status": 429, "errors": [...] }
      ]
    }
  }]
}
```

**However:** If batch is removed from the protocol (see Issue 2), this complication disappears entirely.

---

## Proposed Solutions

### Option A: Semantic HTTP Status (Recommended for Non-Batch)

Map Forrst error codes to appropriate HTTP status codes while keeping `errors` as the canonical error detail.

| Forrst Error Code | HTTP Status |
|-----------------|-------------|
| Success | 200 |
| `INVALID_ARGUMENTS` | 400 |
| `UNAUTHORIZED` | 401 |
| `FORBIDDEN` | 403 |
| `NOT_FOUND` | 404 |
| `CONFLICT` | 409 |
| `RATE_LIMITED` | 429 |
| `INTERNAL_ERROR` | 500 |
| `UNAVAILABLE` | 503 |
| `DEADLINE_EXCEEDED` | 504 |

**For Batch (if retained):** Use 207 Multi-Status

**Benefits:**
- Infrastructure works correctly
- Backwards compatible (clients already check `errors`)
- Clear error origin (transport vs Forrst)

**Spec Change:**
```
HTTP status codes SHOULD reflect the primary error:
- 2xx: Success (single request) or 207 (batch with mixed results)
- 4xx: Client error (first error in `errors` array determines code)
- 5xx: Server error

The `errors` array remains the canonical error detail.
Clients MUST NOT rely solely on HTTP status; always check `errors`.
```

### Option B: Remove Batch, Use Semantic Status

If batch is removed (see Issue 2), the mapping becomes simple:
- Single request → single status code
- No mixed results to handle
- Clean 1:1 mapping

This is the cleanest solution.

---

## Final Recommendation

**Use semantic HTTP status codes.** Batch is removed (Issue 2), so mapping is simple.

When you choose HTTP as transport, speak HTTP. Message queue transports ignore HTTP status anyway.

---

## Migration Path

1. ✅ Batch removed (Issue 2)
2. Update spec to require semantic status codes
3. Clients require no changes (already check `errors` field)
4. Add guidance: "HTTP status is informational; `errors` is authoritative"

---

## Spec Changes Required

1. **transport.md**: Update HTTP section with status code mapping table
2. **errors.md**: Add HTTP status column to error code tables
3. **protocol.md**: Remove "MUST use 200 for all responses" language
