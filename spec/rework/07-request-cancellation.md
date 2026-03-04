---
title: "Issue 7: Request Cancellation"
---

# Issue 7: Request Cancellation

> ✅ **FINAL DECISION:** Add cancellation extension

---

## Decision

**Add `urn:forrst:ext:cancellation` extension for sync request cancellation.**

```json
// Request with cancellation token
{
  "extensions": [{
    "urn": "urn:forrst:ext:cancellation",
    "options": { "token": "cancel_abc789" }
  }]
}

// Cancel via separate request
{
  "call": {
    "function": "urn:cline:forrst:ext:cancellation:fn:cancel",
    "version": "1.0.0",
    "arguments": { "token": "cancel_abc789" }
  }
}
```

---

## Original Problem

The async extension supports cancellation:

```json
{
  "call": {
    "function": "urn:cline:forrst:ext:async:fn:cancel",
    "version": "1.0.0",
    "arguments": { "operation_id": "op_xyz" }
  }
}
```

But synchronous requests have no cancellation mechanism.

### Why This Matters

1. **Long-running sync operations**: Reports, exports, complex queries
2. **User abandonment**: User navigates away, request continues
3. **Timeout recovery**: Client times out, server keeps working
4. **Resource waste**: Completed work is discarded

### Real Scenarios

**Scenario 1: Report Generation**
- Client requests large report (sync)
- Takes 30 seconds
- User clicks cancel at 15 seconds
- Server continues for 15 more seconds
- Result is discarded

**Scenario 2: Client Timeout**
- Deadline: 5 seconds
- Server completes at 6 seconds
- Client already returned `DEADLINE_EXCEEDED`
- Server work wasted

**Scenario 3: Load Shedding**
- Server overloaded
- Wants to cancel in-flight low-priority requests
- No mechanism to do so

---

## Analysis

### HTTP Cancellation Today

HTTP/1.1: Client closes connection → server MAY detect via broken pipe
HTTP/2: Client sends RST_STREAM → server receives cancel signal

But:
- Detection is transport-specific
- Not all servers handle connection close
- Message queues have no equivalent

### Why Async Cancellation Works

Async operations have IDs that persist beyond the request:
- Client gets `operation_id`
- Can call `urn:cline:forrst:ext:async:fn:cancel` later
- Server checks cancellation flag periodically

### Sync Challenge

Sync requests block until complete:
- No ID to reference later
- No separate cancel channel
- Connection state is the only signal

---

## Proposed Solutions

### Option A: Cancellation Token Extension (Recommended)

Add extension for cancellation tokens:

**Request:**
```json
{
  "protocol": "0.1.0",
  "id": "req_123",
  "call": {
    "function": "reports.generate",
    "version": "1.0.0",
    "arguments": { "type": "annual" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:cancellation",
      "options": {
        "token": "cancel_abc789"
      }
    }
  ]
}
```

**Cancel Request (separate connection):**
```json
{
  "protocol": "0.1.0",
  "id": "req_cancel",
  "call": {
    "function": "urn:cline:forrst:ext:cancellation:fn:cancel",
    "version": "1.0.0",
    "arguments": {
      "token": "cancel_abc789"
    }
  }
}
```

**Cancelled Response:**
```json
{
  "protocol": "0.1.0",
  "id": "req_123",
  "result": null,
  "errors": [{
    "code": "CANCELLED",
    "message": "Request cancelled by client",
    "retryable": false
  }]
}
```

**Benefits:**
- Works across transports
- Explicit cancellation semantics
- Server can clean up properly

**Drawbacks:**
- Requires second connection/request
- Token management overhead

### Option B: Connection-Based Cancellation

Document transport-specific cancellation:

**HTTP/2:**
```markdown
Servers SHOULD monitor for RST_STREAM frames.
Upon receiving RST_STREAM, server SHOULD:
1. Stop processing immediately
2. Release resources
3. NOT send response
```

**Message Queues:**
```markdown
Not applicable. Use async extension instead.
```

**Benefits:**
- Uses existing HTTP/2 mechanism
- No protocol changes

**Drawbacks:**
- Transport-specific
- Not all servers implement
- No acknowledgment

### Option C: Deadline as Implicit Cancellation

Treat deadline expiration as cancellation trigger:

```markdown
When deadline expires:
1. Server MUST stop processing
2. Server MUST return DEADLINE_EXCEEDED
3. Server SHOULD release resources immediately
```

**Benefits:**
- Already have deadline extension
- No new mechanism needed

**Drawbacks:**
- Only time-based, not user-initiated
- Can't cancel early

### Option D: Request Registry Extension

Server tracks all in-flight requests:

```json
// List in-flight requests
{
  "call": {
    "function": "forrst.requests.list",
    "version": "1.0.0",
    "arguments": { "caller": "checkout-service" }
  }
}

// Cancel specific request
{
  "call": {
    "function": "forrst.requests.cancel",
    "version": "1.0.0",
    "arguments": { "request_id": "req_123" }
  }
}
```

**Benefits:**
- Uses existing `id` field
- Can cancel any request
- Admin visibility

**Drawbacks:**
- Server must track all requests
- Memory overhead
- Race conditions

---

## Recommendation

**Option A (Cancellation Token)** + **Option C (Deadline)** combined:

1. **Deadlines**: Implicit cancellation on timeout (already exists)
2. **Tokens**: Explicit cancellation for user-initiated abort

### Extension Specification

```markdown
## Cancellation Extension

**URN:** `urn:forrst:ext:cancellation`

Enables explicit request cancellation.

### Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `token` | string | Yes | Unique cancellation token |

### Usage

**Step 1:** Include cancellation token in request:
```json
{
  "extensions": [{
    "urn": "urn:forrst:ext:cancellation",
    "options": { "token": "cancel_abc789" }
  }]
}
```

**Step 2:** To cancel, call `urn:cline:forrst:ext:cancellation:fn:cancel`:
```json
{
  "call": {
    "function": "urn:cline:forrst:ext:cancellation:fn:cancel",
    "version": "1.0.0",
    "arguments": { "token": "cancel_abc789" }
  }
}
```

### Server Behavior

Servers supporting this extension MUST:
1. Track active cancellation tokens
2. Check cancellation status periodically during processing
3. Return `CANCELLED` error if cancelled
4. Clean up resources on cancellation

### Error Code

| Code | Retryable | Description |
|------|-----------|-------------|
| `CANCELLED` | No | Request was cancelled |
| `CANCEL_TOKEN_UNKNOWN` | No | Unknown cancellation token |
| `CANCEL_TOO_LATE` | No | Request already completed |
```

---

## Implementation Notes

### Token Generation

Clients generate tokens (like `id` field):
- UUID: `cancel_550e8400-e29b-41d4-a716-446655440000`
- Random: `cancel_abc789xyz`

### Cancellation Latency

Cancellation is best-effort:
- Server may complete before seeing cancel
- Server may be between check points
- Some work may be wasted

### Cleanup

On cancellation, servers SHOULD:
- Release database connections
- Abort ongoing queries
- Free memory allocations
- Close file handles

---

## Actions Required

1. Create `extensions/cancellation.md` with full specification
2. Add `CANCELLED` error code to `errors.md`
3. Add `urn:cline:forrst:ext:cancellation:fn:cancel` to system functions
4. Add cancellation extension to extension index
