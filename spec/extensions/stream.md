---
title: Stream
description: Server-Sent Events streaming for real-time responses
---

# Stream

> Server-Sent Events streaming for real-time responses

**Extension URN:** `urn:forrst:ext:stream`

---

## Overview

The stream extension enables real-time streaming responses using Server-Sent Events (SSE). Functions that support streaming can send partial results, progress updates, and the final result incrementally rather than waiting for the entire response.

---

## When to Use

Streaming SHOULD be used for:
- AI/LLM token-by-token responses
- Large data exports with progress
- Real-time search results
- Long-running operations with incremental output
- Operations where partial results are immediately useful

Streaming SHOULD NOT be used for:
- Simple CRUD operations
- Small, fast responses
- Operations without meaningful partial results

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `accept` | boolean | Yes | Whether the client accepts streaming |

---

## Data (Response)

The stream extension indicates streaming was used:

| Field | Type | Description |
|-------|------|-------------|
| `enabled` | boolean | Whether streaming was enabled |
| `content_type` | string | Content type (`text/event-stream`) |

---

## Behavior

### Negotiation

1. Client requests streaming with `accept: true`
2. Server checks if function implements `StreamableFunction`
3. If supported, server responds with SSE stream
4. If not supported, server returns `EXTENSION_NOT_APPLICABLE` error

### SSE Event Types

| Event | Description |
|-------|-------------|
| `data` | Partial result data |
| `progress` | Progress update |
| `error` | Error occurred |
| `done` | Final result, stream complete |
| `complete` | Forrst protocol response wrapper |

### Stream Chunk Format

Each SSE event follows standard format:

```
event: data
data: {"content": "partial result..."}

event: progress
data: {"current": 50, "total": 100, "percent": 50, "message": "Processing..."}

event: done
data: {"final": "result"}

event: complete
data: {"protocol": {...}, "id": "req_123", "result": {"streamed": true}, ...}
```

---

## Examples

### Streaming Request

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_stream",
  "call": {
    "function": "ai.generate",
    "version": "1.0.0",
    "arguments": {
      "prompt": "Write a short story about a robot"
    }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:stream",
      "options": {
        "accept": true
      }
    }
  ]
}
```

### SSE Response Stream

```
HTTP/1.1 200 OK
Content-Type: text/event-stream
Cache-Control: no-cache
Connection: keep-alive

event: data
data: {"status":"connected"}

event: data
data: {"content":"Once upon a time"}

event: data
data: {"content":", there was a robot"}

event: data
data: {"content":" named Bolt..."}

event: progress
data: {"current":50,"total":100,"percent":50,"message":"Generating story..."}

event: data
data: {"content":" The end."}

event: done
data: {"content":"Once upon a time, there was a robot named Bolt... The end."}

event: complete
data: {"protocol":{"name":"forrst","version":"0.1.0"},"id":"req_stream","result":{"streamed":true},"extensions":[{"urn":"urn:forrst:ext:stream","data":{"completed":true}}]}
```

### Function Does Not Support Streaming

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_stream",
  "result": null,
  "errors": [{
    "code": "EXTENSION_NOT_APPLICABLE",
    "message": "Function does not support streaming",
    "details": {
      "function": "users.get",
      "extension": "urn:forrst:ext:stream"
    }
  }]
}
```

### Error During Stream

```
event: data
data: {"content":"Starting generation..."}

event: error
data: {"code":"INTERNAL_ERROR","message":"Generation failed: context limit exceeded","details":null}
```

---

## Server Implementation

### StreamableFunction Interface

Functions that support streaming MUST implement `StreamableFunction`:

```php
interface StreamableFunction extends FunctionInterface
{
    /**
     * Stream the function response.
     *
     * @return Generator<int, StreamChunk|mixed>
     */
    public function stream(): Generator;
}
```

### StreamChunk Class

```php
// Data chunk
yield StreamChunk::data(['content' => 'partial result']);

// Progress update
yield StreamChunk::progress(50, 100, 'Processing...');

// Error (final)
yield StreamChunk::error('ERROR_CODE', 'Error message');

// Done (final)
yield StreamChunk::done(['complete' => 'result']);
```

### Example Streamable Function

```php
class GenerateTextFunction extends AbstractFunction implements StreamableFunction
{
    public function stream(): Generator
    {
        $prompt = $this->argument('prompt');

        // Stream tokens as they're generated
        foreach ($this->llm->generateTokens($prompt) as $token) {
            yield StreamChunk::data(['token' => $token]);

            // Check for cancellation
            if ($this->isCancellationRequested()) {
                $this->throwIfCancellationRequested();
            }
        }

        yield StreamChunk::done(['complete' => true]);
    }

    public function execute(): mixed
    {
        // Non-streaming fallback
        return ['text' => $this->llm->generate($this->argument('prompt'))];
    }
}
```

---

## Client Implementation

### JavaScript EventSource

```javascript
const response = await fetch('/forrst', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    protocol: { name: 'forrst', version: '0.1.0' },
    id: 'req_stream',
    call: {
      function: 'ai.generate',
      version: '1',
      arguments: { prompt: 'Hello world' }
    },
    extensions: [{
      urn: 'urn:forrst:ext:stream',
      options: { accept: true }
    }]
  })
});

const reader = response.body.getReader();
const decoder = new TextDecoder();

while (true) {
  const { done, value } = await reader.read();
  if (done) break;

  const text = decoder.decode(value);
  const lines = text.split('\n');

  for (const line of lines) {
    if (line.startsWith('event: ')) {
      currentEvent = line.slice(7);
    } else if (line.startsWith('data: ')) {
      const data = JSON.parse(line.slice(6));
      handleEvent(currentEvent, data);
    }
  }
}
```

---

## Integration with Other Extensions

### With Cancellation

Streaming functions SHOULD check for cancellation:

```php
public function stream(): Generator
{
    foreach ($chunks as $chunk) {
        if ($this->isCancellationRequested()) {
            yield StreamChunk::error('CANCELLED', 'Stream cancelled');
            return;
        }
        yield StreamChunk::data($chunk);
    }
}
```

### With Deadline

Streaming respects deadline extension:

```php
public function stream(): Generator
{
    $deadline = $this->getDeadline();

    foreach ($chunks as $chunk) {
        if ($deadline && $deadline->isPast()) {
            yield StreamChunk::error('DEADLINE_EXCEEDED', 'Stream deadline exceeded');
            return;
        }
        yield $chunk;
    }
}
```

---

## Capabilities

The stream extension advertises its capabilities:

```json
{
  "urn": "urn:forrst:ext:stream",
  "content_type": "text/event-stream",
  "events": ["data", "progress", "error", "done"]
}
```

---

## Error Codes

| Code | Description |
|------|-------------|
| `EXTENSION_NOT_APPLICABLE` | Function does not support streaming |
| `INTERNAL_ERROR` | Error occurred during streaming |
| `CANCELLED` | Stream was cancelled |
| `DEADLINE_EXCEEDED` | Stream exceeded deadline |
