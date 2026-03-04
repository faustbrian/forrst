---
title: Servers
description: Configure and customize Forrst servers for your microservice endpoints
---

Servers define HTTP endpoints that expose Forrst functions. Configure servers via `config/rpc.php` or by extending `AbstractServer`.

## Configuration-Based Servers

The simplest approach uses the configuration file:

```php
// config/rpc.php
return [
    'servers' => [
        [
            'name' => env('APP_NAME'),
            'path' => '/rpc',
            'route' => 'rpc',
            'version' => '1.0.0',
            'middleware' => [
                RenderThrowable::class,
                SubstituteBindings::class,
                'auth:sanctum',
                ForceJson::class,
                BootServer::class,
            ],
            'functions' => null, // Auto-discover
        ],
    ],
];
```

### Server Configuration Options

| Option | Type | Description |
|--------|------|-------------|
| `name` | string | Server name for documentation |
| `path` | string | URL path for the endpoint |
| `route` | string | Laravel route name |
| `version` | string | API version (semantic versioning) |
| `middleware` | array | Middleware stack |
| `functions` | array\|null | Function classes or null for auto-discovery |

## Class-Based Servers

For more control, extend `AbstractServer`:

```php
<?php

namespace App\Http\Servers;

use App\Http\Functions\Orders;
use App\Http\Functions\Users;
use Cline\Forrst\Extensions\CachingExtension;
use Cline\Forrst\Extensions\IdempotencyExtension;
use Cline\Forrst\Extensions\RateLimitExtension;
use Cline\Forrst\Servers\AbstractServer;
use Override;

class ApiServer extends AbstractServer
{
    #[Override()]
    public function getName(): string
    {
        return 'Order Management API';
    }

    #[Override()]
    public function getRoutePath(): string
    {
        return '/api/rpc';
    }

    #[Override()]
    public function getRouteName(): string
    {
        return 'api.rpc';
    }

    #[Override()]
    public function getVersion(): string
    {
        return '2.0.0';
    }

    #[Override()]
    public function getMiddleware(): array
    {
        return [
            'auth:sanctum',
            ForceJson::class,
            BootServer::class,
        ];
    }

    #[Override()]
    public function functions(): array
    {
        return [
            Users\ListFunction::class,
            Users\GetFunction::class,
            Users\CreateFunction::class,
            Orders\ListFunction::class,
            Orders\CreateFunction::class,
        ];
    }

    #[Override()]
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

### Register Class-Based Server

Register in a route file using the `Route::rpc()` mixin:

```php
// routes/api.php
use App\Http\Servers\ApiServer;
use Illuminate\Support\Facades\Route;

Route::rpc(ApiServer::class);
```

Or register manually in your service provider:

```php
use Illuminate\Support\Facades\Route;

public function boot(): void
{
    Route::rpc(new ApiServer());
}
```

## Middleware

### Default Middleware Stack

The recommended middleware order:

```php
'middleware' => [
    RenderThrowable::class,    // Convert exceptions to Forrst errors
    SubstituteBindings::class, // Route model binding
    'auth:sanctum',            // Authentication
    ForceJson::class,          // Ensure JSON content type
    BootServer::class,         // Initialize server context
],
```

### Middleware Descriptions

| Middleware | Purpose |
|------------|---------|
| `RenderThrowable` | Catches exceptions and renders as Forrst error responses |
| `ForceJson` | Enforces JSON content negotiation |
| `BootServer` | Initializes the server context for request processing |
| `SubstituteBindings` | Enables route model binding in function arguments |

### Custom Middleware

Create middleware that integrates with the Forrst request lifecycle:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Cline\Forrst\Data\RequestObjectData;
use Illuminate\Http\Request;

class LogFunctionCalls
{
    public function handle(Request $request, Closure $next)
    {
        $requestObject = $request->attributes->get('forrst.request');

        if ($requestObject instanceof RequestObjectData) {
            logger()->info('Forrst call', [
                'function' => $requestObject->call->function,
                'version' => $requestObject->call->version,
            ]);
        }

        return $next($request);
    }
}
```

## Multiple Servers

Run multiple Forrst servers for different purposes:

```php
// config/rpc.php
return [
    'servers' => [
        // Public API
        [
            'name' => 'Public API',
            'path' => '/api/rpc',
            'route' => 'api.rpc',
            'middleware' => ['auth:sanctum', ForceJson::class, BootServer::class],
            'functions' => [
                Users\ListFunction::class,
                Users\GetFunction::class,
            ],
        ],
        // Internal API (no auth)
        [
            'name' => 'Internal API',
            'path' => '/internal/rpc',
            'route' => 'internal.rpc',
            'middleware' => [ForceJson::class, BootServer::class],
            'functions' => [
                Admin\SyncFunction::class,
                Admin\CacheFlushFunction::class,
            ],
        ],
    ],
];
```

## Function Discovery

### Auto-Discovery

Set `functions` to `null` to auto-discover from the configured path:

```php
'namespaces' => [
    'functions' => 'App\\Http\\Functions',
],

'paths' => [
    'functions' => app_path('Http/Functions'),
],

'servers' => [
    [
        'functions' => null, // Discovers all functions in path
    ],
],
```

### Selective Exposure

Specify exactly which functions to expose:

```php
'functions' => [
    Users\ListFunction::class,
    Users\GetFunction::class,
    // Users\DeleteFunction::class is NOT exposed
],
```

### Wildcard Patterns

Use patterns for function groups (in class-based servers):

```php
public function functions(): array
{
    return [
        ...app(FunctionDiscovery::class)->find('App\\Http\\Functions\\Users'),
        ...app(FunctionDiscovery::class)->find('App\\Http\\Functions\\Orders'),
    ];
}
```

## Resource Mapping

Map Eloquent models to Forrst resources for consistent transformations:

```php
// config/rpc.php
return [
    'resources' => [
        \App\Models\User::class => \App\Http\Resources\UserResource::class,
        \App\Models\Order::class => \App\Http\Resources\OrderResource::class,
    ],
];
```

Resources implement `ResourceInterface`:

```php
<?php

namespace App\Http\Resources;

use Cline\Forrst\Contracts\ResourceInterface;

class UserResource implements ResourceInterface
{
    public function __construct(
        private User $user,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'created_at' => $this->user->created_at->toIso8601String(),
        ];
    }
}
```

## Server Lifecycle

### Boot Process

1. Service provider reads `config/rpc.php`
2. Resource mappings registered in `ResourceRepository`
3. Each server configuration creates a `ConfigurationServer` instance
4. `Route::rpc()` mixin registers the POST route
5. Functions auto-discovered or explicitly registered

### Request Lifecycle

1. Request received at server path
2. Middleware stack executed
3. `BootServer` middleware sets server context
4. Protocol decodes request
5. Function resolved from repository
6. Extensions run (before, around, after)
7. Function executed
8. Response encoded and returned

## Production Considerations

### Caching Function Discovery

In production, cache function discovery for performance:

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    if ($this->app->environment('production')) {
        $this->app->make(FunctionRepository::class)->cache();
    }
}
```

### Health Checks

Add a health check function:

```php
class HealthFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        return [
            'status' => 'healthy',
            'version' => config('app.version'),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

### Monitoring

Enable tracing for distributed request tracking:

```php
public function extensions(): array
{
    return [
        new TracingExtension(),
    ];
}
```

## Next Steps

- **[Functions](functions)** - Implement function handlers with validation and authentication
- **[Extensions](extensions)** - Add cross-cutting concerns like caching and rate limiting
- **[Protocol Specification](spec/)** - Understand the Forrst wire protocol
