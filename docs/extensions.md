---
title: Extensions
description: Add cross-cutting functionality like caching, idempotency, and rate limiting
---

Extensions provide optional capabilities that enhance Forrst functions with cross-cutting concerns like caching, idempotency, rate limiting, and observability.

## Built-in Extensions

| Extension | Purpose |
|-----------|---------|
| `CachingExtension` | HTTP-style caching with ETags and conditional requests |
| `DeadlineExtension` | Request timeouts and deadline propagation |
| `DeprecationExtension` | Mark functions as deprecated with migration guidance |
| `DryRunExtension` | Validate requests without side effects |
| `IdempotencyExtension` | Prevent duplicate operations |
| `LocaleExtension` | Localization and internationalization |
| `MaintenanceExtension` | Scheduled maintenance windows |
| `PriorityExtension` | Request prioritization |
| `QueryExtension` | Rich filtering, sorting, and pagination |
| `QuotaExtension` | Usage quotas and limits |
| `RateLimitExtension` | Throttle requests |
| `RedactExtension` | Redact sensitive data in responses |
| `ReplayExtension` | Request replay for debugging |
| `RetryExtension` | Automatic retry with backoff |
| `SimulationExtension` | Sandbox mode with simulated responses |
| `StreamExtension` | Streaming responses |
| `TracingExtension` | Distributed tracing context |

## Registering Extensions

### In Configuration

```php
// config/rpc.php
'servers' => [
    [
        'extensions' => [
            \Cline\Forrst\Extensions\CachingExtension::class,
            \Cline\Forrst\Extensions\IdempotencyExtension::class,
        ],
    ],
],
```

### In Server Classes

```php
use Cline\Forrst\Extensions\CachingExtension;
use Cline\Forrst\Extensions\IdempotencyExtension;
use Cline\Forrst\Extensions\RateLimitExtension;
use Cline\Forrst\Servers\AbstractServer;

class ApiServer extends AbstractServer
{
    public function extensions(): array
    {
        return [
            new CachingExtension(cache: cache()->store()),
            new IdempotencyExtension(),
            new RateLimitExtension(maxAttempts: 60, decayMinutes: 1),
        ];
    }
}
```

## Caching Extension

Implements HTTP-style caching with ETags and conditional requests:

```php
use Cline\Forrst\Extensions\CachingExtension;

new CachingExtension(
    cache: cache()->store('redis'),
    defaultTtl: 300, // 5 minutes
);
```

### Client Request

```json
{
  "call": { "function": "urn:acme:forrst:fn:users:list" },
  "extensions": {
    "caching": {
      "if_none_match": "\"abc123\"",
      "if_modified_since": "2025-01-15T10:00:00Z"
    }
  }
}
```

### Response with Cache Headers

```json
{
  "result": [...],
  "extensions": {
    "caching": {
      "etag": "\"def456\"",
      "last_modified": "2025-01-15T12:30:00Z",
      "max_age": 300,
      "cache_status": "miss"
    }
  }
}
```

### Cache Status Values

| Status | Description |
|--------|-------------|
| `hit` | Client's cached copy is valid |
| `miss` | Fresh response generated |
| `stale` | Cached copy exists but outdated |
| `bypass` | Caching intentionally bypassed |

## Idempotency Extension

Prevents duplicate operations using idempotency keys:

```php
use Cline\Forrst\Extensions\IdempotencyExtension;

new IdempotencyExtension();
```

### Client Request

```json
{
  "call": {
    "function": "urn:acme:forrst:fn:payments:charge",
    "arguments": { "amount": 99.99, "customer_id": 123 }
  },
  "extensions": {
    "idempotency": {
      "key": "charge-123-abc"
    }
  }
}
```

If the same key is sent again within the TTL, the cached response is returned without re-executing the function.

## Rate Limit Extension

Throttles requests to prevent abuse:

```php
use Cline\Forrst\Extensions\RateLimitExtension;

new RateLimitExtension(
    maxAttempts: 60,      // requests per window
    decayMinutes: 1,      // window duration
);
```

### Response Headers

```json
{
  "extensions": {
    "rate_limit": {
      "limit": 60,
      "remaining": 45,
      "reset_at": "2025-01-15T10:01:00Z"
    }
  }
}
```

### Rate Limit Exceeded

```json
{
  "errors": [{
    "code": "RATE_LIMITED",
    "message": "Too many requests",
    "details": {
      "retry_after": 45
    }
  }]
}
```

## Deadline Extension

Enforces request timeouts:

```php
use Cline\Forrst\Extensions\DeadlineExtension;

new DeadlineExtension(
    defaultTimeout: 30, // seconds
);
```

### Client Request

```json
{
  "call": { "function": "urn:acme:forrst:fn:reports:generate" },
  "extensions": {
    "deadline": {
      "timeout": "60s",
      "absolute": "2025-01-15T10:05:00Z"
    }
  }
}
```

Functions can check remaining time:

```php
class ReportGenerateFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        // Check remaining deadline time
        if ($this->getDeadlineRemaining() < 5) {
            return $this->partialResult();
        }

        return $this->generateFullReport();
    }
}
```

## Query Extension

Rich filtering, sorting, and pagination for list functions:

```php
use Cline\Forrst\Extensions\QueryExtension;

new QueryExtension();
```

### Client Request

```json
{
  "call": {
    "function": "urn:acme:forrst:fn:users:list",
    "arguments": {}
  },
  "extensions": {
    "query": {
      "filter": {
        "status": { "eq": "active" },
        "created_at": { "gte": "2025-01-01" }
      },
      "sort": [
        { "field": "name", "direction": "asc" }
      ],
      "page": {
        "size": 25,
        "number": 1
      }
    }
  }
}
```

### Filter Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `eq` | Equals | `{ "status": { "eq": "active" } }` |
| `neq` | Not equals | `{ "status": { "neq": "deleted" } }` |
| `gt` | Greater than | `{ "age": { "gt": 18 } }` |
| `gte` | Greater or equal | `{ "created_at": { "gte": "2025-01-01" } }` |
| `lt` | Less than | `{ "price": { "lt": 100 } }` |
| `lte` | Less or equal | `{ "stock": { "lte": 10 } }` |
| `in` | In array | `{ "status": { "in": ["active", "pending"] } }` |
| `contains` | String contains | `{ "name": { "contains": "john" } }` |

## Deprecation Extension

Marks functions as deprecated with migration guidance:

```php
use Cline\Forrst\Extensions\DeprecationExtension;

new DeprecationExtension();
```

### In Function Descriptor

```php
FunctionDescriptor::make()
    ->urn('urn:acme:forrst:fn:users:list')
    ->deprecated(
        DeprecatedData::make('2.0.0', 'Use urn:acme:forrst:fn:users:search')
            ->removedIn('3.0.0')
    );
```

### Response Warning

```json
{
  "result": [...],
  "extensions": {
    "deprecation": {
      "warning": "This function is deprecated since v2.0.0",
      "replacement": "urn:acme:forrst:fn:users:search",
      "removed_in": "3.0.0"
    }
  }
}
```

## Tracing Extension

Propagates distributed tracing context:

```php
use Cline\Forrst\Extensions\TracingExtension;

new TracingExtension();
```

### Request Context

```json
{
  "call": { "function": "urn:acme:forrst:fn:orders:create" },
  "context": {
    "trace_id": "abc123",
    "span_id": "def456",
    "parent_span_id": "ghi789"
  }
}
```

### Response Context

```json
{
  "result": {...},
  "context": {
    "trace_id": "abc123",
    "span_id": "jkl012",
    "parent_span_id": "def456",
    "duration_ms": 45
  }
}
```

## Creating Custom Extensions

### Extension Anatomy

```php
<?php

namespace App\Extensions;

use Cline\Forrst\Extensions\AbstractExtension;
use Override;

class AuditExtension extends AbstractExtension
{
    #[Override()]
    public function getUrn(): string
    {
        return 'urn:acme:forrst:ext:audit';
    }

    #[Override()]
    public function isGlobal(): bool
    {
        return true; // Runs on all requests
    }

    #[Override()]
    public function isErrorFatal(): bool
    {
        return false; // Errors don't fail the request
    }

    #[Override()]
    public function getSubscribedEvents(): array
    {
        return [
            FunctionExecuted::class => [
                'priority' => 100,
                'method' => 'onFunctionExecuted',
            ],
        ];
    }

    public function onFunctionExecuted(FunctionExecuted $event): void
    {
        AuditLog::create([
            'function' => $event->function->getName(),
            'user_id' => auth()->id(),
            'arguments' => $event->request->arguments,
            'response' => $event->response->result,
        ]);
    }
}
```

### Extension Lifecycle Events

| Event | When Fired |
|-------|------------|
| `RequestReceived` | Request parsed, before validation |
| `RequestValidated` | Request validated, before function resolution |
| `ExecutingFunction` | Function resolved, before execution |
| `FunctionExecuted` | Function completed successfully |
| `SendingResponse` | Response prepared, before encoding |
| `RequestFailed` | Error occurred during processing |

### Extension with Request Modification

```php
class TenantExtension extends AbstractExtension
{
    public function getSubscribedEvents(): array
    {
        return [
            RequestValidated::class => [
                'priority' => 50,
                'method' => 'injectTenant',
            ],
        ];
    }

    public function injectTenant(RequestValidated $event): void
    {
        // Add tenant to all queries automatically
        $tenantId = auth()->user()?->tenant_id;

        $event->request = $event->request->withArgument(
            'tenant_id',
            $tenantId,
        );
    }
}
```

### Extension with Response Enrichment

```php
class TimingExtension extends AbstractExtension
{
    private float $startTime;

    public function getSubscribedEvents(): array
    {
        return [
            RequestReceived::class => [
                'priority' => 0,
                'method' => 'startTimer',
            ],
            SendingResponse::class => [
                'priority' => 1000,
                'method' => 'addTiming',
            ],
        ];
    }

    public function startTimer(RequestReceived $event): void
    {
        $this->startTime = microtime(true);
    }

    public function addTiming(SendingResponse $event): void
    {
        $duration = (microtime(true) - $this->startTime) * 1000;

        $event->response = $event->response->withExtension(
            'timing',
            ['duration_ms' => round($duration, 2)],
        );
    }
}
```

## Extension Priority

Lower priority numbers run first. Use these guidelines:

| Priority Range | Purpose |
|----------------|---------|
| 0-49 | Early processing (timing, validation) |
| 50-99 | Request modification (tenant injection) |
| 100-149 | Core functionality (caching, idempotency) |
| 150-199 | Response modification |
| 200+ | Late processing (logging, metrics) |

## Global vs Opt-in Extensions

### Global Extensions

Run on every request automatically:

```php
public function isGlobal(): bool
{
    return true; // Tracing, timing, audit logging
}
```

### Opt-in Extensions

Only run when client requests them:

```php
public function isGlobal(): bool
{
    return false; // Caching, idempotency, dry-run
}
```

Client requests opt-in extensions:

```json
{
  "extensions": {
    "caching": { "ttl": 300 },
    "idempotency": { "key": "..." }
  }
}
```

## Next Steps

- **[Protocol Specification](spec/)** - Deep dive into the Forrst wire protocol
- **[Extension Specifications](spec/extensions/)** - Detailed specs for each extension
- **[Functions](functions)** - Build function handlers that use extensions
