---
title: "Issue 8: Streaming Support"
---

# Issue 8: Streaming Support

> ✅ **FINAL DECISION:** SSE with reserved extension URN

---

## Decision

**Use Server-Sent Events for streaming. Reserve `urn:forrst:ext:stream` URN for future specification.**

Simple SSE implementation with standard PHP:

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

No special runtime required — works with PHP-FPM + Nginx.

---

## Original Problem

Streaming was explicitly removed from the specification, but many modern use cases require it:

1. **LLM Responses**: Token-by-token generation (ChatGPT, Claude)
2. **File Uploads/Downloads**: Large binary transfers
3. **Real-time Feeds**: Activity streams, notifications
4. **Progress Updates**: Long operation status
5. **Log Tailing**: Continuous log output

### Current State

The spec says nothing about streaming. The FAQ doesn't address it. The memory context shows:
> "Streaming Purged from Specifications"

### Why This Matters

Without streaming:
- LLM integrations must buffer entire responses
- Large files require separate protocols
- Progress requires polling
- Real-time features need WebSockets alongside Forrst

---

## Analysis

### Why Streaming is Hard

1. **Transport Variance**: HTTP/2 streams vs WebSocket vs SSE vs message queues
2. **Error Handling**: What happens mid-stream?
3. **Backpressure**: What if client can't keep up?
4. **Framing**: How to delimit chunks?
5. **Cancellation**: How to stop a stream?

### Why It Was Removed

Likely reasons:
- Complexity vs value for v0.1
- Transport-agnostic goal conflicts with streaming
- Focus on request/response simplicity

### What Other Protocols Do

**gRPC:**
- Server streaming, client streaming, bidirectional
- Built on HTTP/2 streams
- Explicit stream types in proto

**JSON-RPC:**
- No streaming (same limitation)
- Extensions like JSON-RPC over WebSocket add it

**GraphQL:**
- Subscriptions for real-time
- @stream/@defer for incremental delivery

---

## Proposed Solutions

### Option A: Streaming Extension (Recommended)

Define streaming as an extension, not core protocol:

**Request:**
```json
{
  "protocol": "0.1.0",
  "id": "req_123",
  "call": {
    "function": "llm.generate",
    "version": "1.0.0",
    "arguments": { "prompt": "Write a story" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:stream",
      "options": {
        "mode": "server",
        "format": "ndjson"
      }
    }
  ]
}
```

**Streamed Response (multiple chunks):**
```
{"chunk": "Once", "index": 0, "done": false}
{"chunk": " upon", "index": 1, "done": false}
{"chunk": " a time", "index": 2, "done": false}
{"chunk": "", "index": 3, "done": true, "result": {...}}
```

**Stream Modes:**

| Mode | Description |
|------|-------------|
| `server` | Server streams to client |
| `client` | Client streams to server |
| `bidirectional` | Both directions |

**Benefits:**
- Optional (non-streaming stays simple)
- Transport can vary implementation
- Clear semantics

### Option B: Reserved for Future

Add placeholder without specification:

```markdown
## Streaming (Reserved)

Streaming semantics are reserved for future specification.
The extension URN `urn:forrst:ext:stream` is reserved.

Implementations MUST NOT use this URN until specified.
```

**Benefits:**
- Acknowledges the need
- Prevents conflicting implementations
- Buys time for design

### Option C: Transport-Specific Streaming

Document streaming per transport, not in core:

**HTTP Transport:**
```markdown
### Server-Sent Events (SSE)

For server-to-client streaming, use SSE:
1. Client sets `Accept: text/event-stream`
2. Server returns `Content-Type: text/event-stream`
3. Each event is a complete Forrst response
```

**WebSocket Transport:**
```markdown
### WebSocket Streaming

For bidirectional streaming:
1. Establish WebSocket connection
2. Send Forrst requests as messages
3. Receive Forrst responses as messages
4. Multiple responses per request allowed
```

**Benefits:**
- Uses established patterns
- No new protocol concepts

**Drawbacks:**
- Fragmented implementation
- Different semantics per transport

### Option D: Out-of-Band Streaming

Don't stream through Forrst; return streaming URL:

```json
// Request
{
  "call": {
    "function": "llm.generate",
    "version": "1.0.0",
    "arguments": { "prompt": "Write a story" }
  }
}

// Response
{
  "result": {
    "stream_url": "wss://api.example.com/streams/abc123",
    "stream_token": "token_xyz",
    "format": "ndjson"
  }
}
```

**Benefits:**
- Forrst stays request/response
- Streaming is separate concern
- More flexible

**Drawbacks:**
- Two protocols to implement
- Authentication complexity
- Indirection overhead

---

## Recommendation

**Option A (Streaming Extension)** for future + **Option B (Reserved)** for now:

### Immediate (v0.1.0)

Reserve the extension URN and document intent:

```markdown
## Streaming (Reserved)

The streaming extension (`urn:forrst:ext:stream`) is reserved for future specification.

**Current alternatives:**
- Use async extension with polling for long operations
- Use transport-native streaming (SSE, WebSocket) alongside Forrst
- Return URLs to dedicated streaming endpoints
```

### Future (v0.2.0 or v1.0)

Full streaming extension with:

1. **Stream modes**: server, client, bidirectional
2. **Chunk format**: NDJSON with metadata
3. **Error handling**: Error chunks, stream abort
4. **Backpressure**: Flow control signals
5. **Cancellation**: Integration with cancellation extension

---

## Streaming Extension Sketch

For future reference:

### Extension URN

`urn:forrst:ext:stream`

### Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `mode` | string | Yes | `server`, `client`, `bidirectional` |
| `format` | string | No | Chunk format: `ndjson` (default), `raw` |

### Chunk Format (NDJSON)

Each line is a JSON object:

```json
{"seq": 0, "data": "chunk content", "done": false}
{"seq": 1, "data": "more content", "done": false}
{"seq": 2, "data": null, "done": true, "result": {...}}
```

| Field | Type | Description |
|-------|------|-------------|
| `seq` | integer | Sequence number |
| `data` | any | Chunk payload |
| `done` | boolean | Stream complete |
| `result` | object | Final result (when done) |
| `error` | object | Error (when done with failure) |

### Error Mid-Stream

```json
{"seq": 5, "data": null, "done": true, "error": {"code": "INTERNAL_ERROR", ...}}
```

### Transport Bindings

| Transport | Mechanism |
|-----------|-----------|
| HTTP/1.1 | Chunked transfer encoding |
| HTTP/2 | DATA frames |
| WebSocket | Message per chunk |
| SSE | Event per chunk |
| Message Queue | Multiple messages, correlated |

---

## Interim Guidance

Until streaming is specified:

1. **LLM/AI**: Use provider's native streaming, not Forrst
2. **File transfer**: Use presigned URLs to object storage
3. **Progress**: Use async extension with polling
4. **Real-time**: Use WebSocket alongside Forrst for events

---

## PHP Streaming Tooling (Battle-Tested)

For the PHP implementation of Forrst streaming, here are the production-ready options:

### Performance Comparison (2025 Benchmarks)

| Runtime | Requests/sec | Memory (100 concurrent) |
|---------|--------------|------------------------|
| Nginx + PHP-FPM | 1,000 | ~230 MB |
| [RoadRunner](https://roadrunner.dev/) | 1,300 | ~30 MB |
| [OpenSwoole](https://openswoole.com/) | 1,500 | ~30 MB |
| [Swoole](https://www.swoole.co.uk/) | ~8,766 | Low |
| ReactPHP | ~2,516 | Low |

Source: [PHP Runtime Benchmark](https://github.com/cracksalad/PHP-Runtime-Benchmark), [DeployHQ 2025 Comparison](https://www.deployhq.com/blog/comparing-php-application-servers-in-2025-performance-scalability-and-modern-options)

### Recommended Options

#### 1. **Server-Sent Events (SSE)** — Simplest for One-Way Streaming

Works with standard PHP-FPM, no special runtime needed:

```php
// Laravel example
return response()->stream(function () {
    while ($generating) {
        echo "data: " . json_encode(['chunk' => $token]) . "\n\n";
        ob_flush();
        flush();
    }
}, 200, [
    'Content-Type' => 'text/event-stream',
    'Cache-Control' => 'no-cache',
    'X-Accel-Buffering' => 'no',  // Nginx
]);
```

**Packages:**
- [qruto/laravel-wave](https://github.com/qruto/laravel-wave) — SSE broadcasting for Laravel
- [sarfraznawaz2005/laravel-sse](https://github.com/sarfraznawaz2005/laravel-sse) — Simple SSE for Laravel

**Best for:** LLM token streaming, progress updates, activity feeds

Source: [Server Side Up - SSE with Laravel](https://serversideup.net/blog/sending-server-sent-events-with-laravel/)

#### 2. **Swoole** — Highest Performance

C extension with coroutines, built-in HTTP server:

```php
$server = new Swoole\Http\Server('0.0.0.0', 9501);
$server->on('request', function ($request, $response) {
    $response->header('Content-Type', 'text/event-stream');
    while ($generating) {
        $response->write("data: $chunk\n\n");
        Swoole\Coroutine::sleep(0.01);
    }
    $response->end();
});
```

**Pros:** Fastest option, coroutines, production-proven
**Cons:** C extension required, learning curve

**Laravel Integration:** Laravel Octane with Swoole driver

#### 3. **RoadRunner** — Go-Based, Easy Setup

Go application server with PHP workers:

```yaml
# .rr.yaml
http:
  address: 0.0.0.0:8080
  middleware: ["headers"]
  pool:
    num_workers: 4
```

**Pros:** No PHP extensions, good performance, easy deployment
**Cons:** Requires Go binary

**Laravel Integration:** Laravel Octane with RoadRunner driver

Source: [Dave Gebler - RoadRunner](https://davegebler.com/post/php/turbo-charge-your-php-applications-with-roadrunner)

#### 4. **ReactPHP** — Pure PHP Event Loop

No extensions, pure PHP:

```php
$loop = React\EventLoop\Loop::get();
$server = new React\Http\HttpServer(function ($request) {
    $stream = new ThroughStream();
    // Write chunks to $stream
    return new React\Http\Message\Response(200, ['Content-Type' => 'text/event-stream'], $stream);
});
```

**Pros:** Pure PHP, no extensions, Laravel Reverb uses it
**Cons:** Single-threaded, lower throughput than Swoole

#### 5. **FrankenPHP** — Modern, Caddy-Based

Built on Caddy web server with native PHP support:

**Pros:** Native SSE, HTTP/3 support, early hints
**Cons:** Newer, less production history

### Decision Matrix

| Use Case | Recommended Tool |
|----------|------------------|
| Simple SSE (LLM streaming) | Standard PHP + `response()->stream()` |
| High-throughput streaming | Swoole or OpenSwoole |
| Easy deployment, no extensions | RoadRunner |
| WebSocket bidirectional | Laravel Reverb (ReactPHP) or Swoole |
| Maximum compatibility | SSE with PHP-FPM |

### Production Considerations

1. **Database Connections**: Async runtimes keep connections open — implement reconnection logic
2. **Memory Leaks**: Long-running processes need careful memory management
3. **Nginx Buffering**: Disable with `X-Accel-Buffering: no` header
4. **PHP Output Buffering**: Enable in php.ini for streaming to work

Sources:
- [Medium - SSE in Laravel](https://medium.com/@ahmedm3bead/implementing-server-sent-events-sse-in-laravel-a01da635485b)
- [Ahmad Rosid - OpenAI Streaming in Laravel](https://ahmadrosid.com/blog/laravel-openai-streaming-response)
- [Alejandro Celaya - Async PHP Considerations](https://alejandrocelaya.blog/2020/04/09/considerations-when-working-with-async-php-runtimes-like-swoole/)

---

## Actions Required

1. Reserve `urn:forrst:ext:stream` URN in extension index
2. Add interim guidance for SSE usage in transport.md
3. Add SSE example to best-practices.md
4. Full streaming extension specification deferred to later version
