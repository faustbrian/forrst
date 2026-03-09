## Table of Contents

1. [Getting Started](#doc-docs-getting-started) (`docs/getting-started.md`)
2. [Servers](#doc-docs-servers) (`docs/servers.md`)
3. [Functions](#doc-docs-functions) (`docs/functions.md`)
4. [Extensions](#doc-docs-extensions) (`docs/extensions.md`)
5. [Clients](#doc-docs-clients) (`docs/clients.md`)
6. [Testing](#doc-docs-testing) (`docs/testing.md`)
7. [Request Validated Priority](#doc-docs-events-request-validated-priority) (`docs/events/request-validated-priority.md`)
8. [Naming Conventions](#doc-docs-naming-conventions) (`docs/naming-conventions.md`)
<a id="doc-docs-getting-started"></a>

---
title: Getting Started
description: Install and configure Forrst, an internal microservice RPC protocol with per-function versioning
---

Forrst is an internal microservice RPC protocol designed for intra-service communication with per-function versioning, built-in observability, and rich query capabilities.

## Installation

Install via Composer:

```bash
composer require cline/forrst
```

## Requirements

- PHP 8.5+
- Laravel 12+
- spatie/laravel-data 4.18+
- saloonphp/saloon 3.14+

## Laravel Integration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Cline\Forrst\ServiceProvider"
```

This creates `config/rpc.php` with server, function, and resource configuration options.

## Quick Start

### 1. Create Your First Function

Functions are the core building blocks in Forrst. Create a function class:

```php
<?php

namespace App\Http\Functions;

use Cline\Forrst\Functions\AbstractFunction;

class UserListFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->get()
            ->toArray();
    }
}
```

### 2. Configure the Server

In `config/rpc.php`, configure your Forrst server:

```php
return [
    'namespaces' => [
        'functions' => 'App\\Http\\Functions',
    ],

    'paths' => [
        'functions' => app_path('Http/Functions'),
    ],

    'servers' => [
        [
            'name' => env('APP_NAME'),
            'path' => '/rpc',
            'route' => 'rpc',
            'version' => '1.0.0',
            'functions' => null, // Auto-discover all functions
        ],
    ],
];
```

### 3. Make Your First Request

Send a Forrst request using cURL:

```bash
curl -X POST http://localhost/rpc \
  -H "Content-Type: application/json" \
  -d '{
    "protocol": { "name": "forrst", "version": "0.1.0" },
    "id": "req_001",
    "call": {
      "function": "urn:acme:forrst:fn:users:list",
      "version": "1.0.0",
      "arguments": {}
    }
  }'
```

Response:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "result": [
    { "id": 1, "name": "Jane Doe", "email": "jane@example.com" },
    { "id": 2, "name": "John Smith", "email": "john@example.com" }
  ]
}
```

## Core Concepts

### Functions

Functions handle business logic. They extend `AbstractFunction` and implement `__invoke()`:

```php
class OrderCreateFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        $order = Order::create([
            'user_id' => $this->requestObject->arguments['user_id'],
            'total' => $this->requestObject->arguments['total'],
        ]);

        return $order->toArray();
    }
}
```

### Servers

Servers define endpoints that expose functions. Configure via `config/rpc.php` or extend `AbstractServer`:

```php
class ApiServer extends AbstractServer
{
    public function functions(): array
    {
        return [
            UserListFunction::class,
            UserGetFunction::class,
            OrderCreateFunction::class,
        ];
    }

    public function extensions(): array
    {
        return [
            new CachingExtension(cache: cache()->store()),
            new IdempotencyExtension(),
        ];
    }
}
```

### Extensions

Extensions add cross-cutting functionality:

- **CachingExtension** - HTTP-style caching with ETags
- **IdempotencyExtension** - Prevent duplicate operations
- **DeadlineExtension** - Request timeouts
- **QueryExtension** - Rich filtering and pagination
- **RateLimitExtension** - Throttle requests

### Descriptors

Separate discovery metadata from function logic using the `#[Descriptor]` attribute:

```php
use Cline\Forrst\Attributes\Descriptor;

#[Descriptor(UserListDescriptor::class)]
class UserListFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        // Pure business logic
    }
}
```

```php
use Cline\Forrst\Discovery\FunctionDescriptor;
use Cline\Forrst\Contracts\DescriptorInterface;

class UserListDescriptor implements DescriptorInterface
{
    public static function create(): FunctionDescriptor
    {
        return FunctionDescriptor::make()
            ->urn('urn:acme:forrst:fn:users:list')
            ->version('1.0.0')
            ->summary('List all users')
            ->description('Retrieves a paginated list of users with optional filtering');
    }
}
```

## Protocol Discovery

Every Forrst server includes `forrst.describe` for automatic API discovery:

```bash
curl -X POST http://localhost/rpc \
  -H "Content-Type: application/json" \
  -d '{
    "protocol": { "name": "forrst", "version": "0.1.0" },
    "id": "discover_001",
    "call": {
      "function": "urn:cline:forrst:ext:discovery:fn:describe",
      "version": "1.0.0",
      "arguments": {}
    }
  }'
```

## Error Handling

Forrst uses structured error responses:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_001",
  "result": null,
  "errors": [{
    "code": "NOT_FOUND",
    "message": "User not found",
    "details": { "user_id": 999 }
  }]
}
```

Throw custom exceptions in your functions:

```php
use Cline\Forrst\Exceptions\FunctionException;

class UserGetFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        $user = User::find($this->requestObject->arguments['id']);

        if (!$user) {
            throw FunctionException::notFound('User not found');
        }

        return $user->toArray();
    }
}
```

## Testing

Use the `post_forrst` helper in tests:

```php
use function Cline\Forrst\post_forrst;

test('lists users', function () {
    $response = post_forrst('urn:acme:forrst:fn:users:list');

    $response->assertOk();
    $response->assertJsonPath('result.0.name', 'Jane Doe');
});

test('creates order with parameters', function () {
    $response = post_forrst('urn:acme:forrst:fn:orders:create', [
        'user_id' => 1,
        'total' => 99.99,
    ]);

    $response->assertOk();
    $response->assertJsonPath('result.user_id', 1);
});
```

## Next Steps

- **[Servers](servers)** - Configure servers with middleware, extensions, and custom routing
- **[Functions](functions)** - Build functions with validation, authentication, and transformers
- **[Extensions](extensions)** - Add caching, idempotency, rate limiting, and more
- **[Clients](clients)** - Create type-safe Forrst clients using Saloon
- **[Protocol Specification](spec/)** - Deep dive into the Forrst protocol

<a id="doc-docs-servers"></a>

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

<a id="doc-docs-functions"></a>

---
title: Functions
description: Build Forrst functions with validation, authentication, and transformers
---

Functions are the core building blocks of Forrst. They handle business logic and are automatically exposed through servers.

## Basic Function

Extend `AbstractFunction` and implement `__invoke()`:

```php
<?php

namespace App\Http\Functions;

use Cline\Forrst\Functions\AbstractFunction;

class UserListFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        return User::all()->toArray();
    }
}
```

## Function URNs

Functions use URNs (Uniform Resource Names) for globally unique identification:

```
urn:<vendor>:forrst:fn:<name>
```

| Segment | Description | Example |
|---------|-------------|---------|
| `vendor` | Your organization identifier | `acme` |
| `fn` | Resource type (function) | `fn` |
| `name` | Hierarchical function name (kebab-case) | `orders:list` |

### Examples

```
urn:acme:forrst:fn:orders:list
urn:acme:forrst:fn:orders:create
urn:acme:forrst:fn:orders:get
urn:acme:forrst:fn:users:authenticate
```

### Setting the URN

Override `getUrn()` in your function:

```php
public function getUrn(): string
{
    return 'urn:acme:forrst:fn:orders:list';
}
```

Or use the descriptor pattern for clean separation (see Descriptors section).

## Accessing Request Data

The `$this->requestObject` property provides access to the current request:

```php
class UserGetFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        // Access arguments
        $userId = $this->requestObject->arguments['id'];

        // Access request metadata
        $requestId = $this->requestObject->id;

        // Access extension options
        $extensions = $this->requestObject->extensions;

        return User::findOrFail($userId)->toArray();
    }
}
```

### Request Object Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string | Unique request identifier |
| `call` | CallData | Function name, version, arguments |
| `arguments` | array | Shortcut to `call->arguments` |
| `extensions` | array | Extension-specific request options |
| `context` | ContextData | Tracing and observability context |

## Authentication

The `InteractsWithAuthentication` trait provides authentication helpers:

```php
use Cline\Forrst\Functions\AbstractFunction;

class ProfileGetFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        // Get the authenticated user
        $user = $this->getCurrentUser();

        if (!$user) {
            throw new AuthenticationException('Not authenticated');
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
```

### Authentication Methods

```php
// Get current authenticated user
$user = $this->getCurrentUser();

// Get auth guard
$guard = $this->getGuard();

// Check if authenticated
$isAuthenticated = $this->isAuthenticated();

// Get user ID
$userId = $this->getUserId();
```

## Query Building

The `InteractsWithQueryBuilder` trait enables rich queries for list functions:

```php
use Cline\Forrst\Functions\AbstractListFunction;

class UserListFunction extends AbstractListFunction
{
    protected function getModel(): string
    {
        return User::class;
    }

    public function __invoke(): array
    {
        return $this->query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
```

### Query with Request Parameters

```php
class OrderListFunction extends AbstractListFunction
{
    public function __invoke(): array
    {
        $query = $this->query();

        // Apply filters from request arguments
        if ($status = $this->requestObject->arguments['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($userId = $this->requestObject->arguments['user_id'] ?? null) {
            $query->where('user_id', $userId);
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }
}
```

## Data Transformation

The `InteractsWithTransformer` trait provides transformation helpers:

```php
class UserListFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        $users = User::all();

        // Transform using registered resource
        return $this->transform($users);
    }
}
```

### Pagination

```php
class UserListFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        $perPage = $this->requestObject->arguments['per_page'] ?? 15;

        // Standard pagination
        return $this->paginate(
            User::query()->orderBy('name'),
            $perPage
        );

        // Simple pagination (no total count)
        return $this->simplePaginate(
            User::query()->orderBy('name'),
            $perPage
        );

        // Cursor pagination
        return $this->cursorPaginate(
            User::query()->orderBy('id'),
            $perPage
        );
    }
}
```

## Cancellation Checking

The `InteractsWithCancellation` trait supports request cancellation:

```php
class BulkProcessFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        $items = $this->requestObject->arguments['items'];
        $processed = [];

        foreach ($items as $item) {
            // Check if request was cancelled
            if ($this->isCancelled()) {
                return [
                    'status' => 'cancelled',
                    'processed' => $processed,
                ];
            }

            $processed[] = $this->processItem($item);
        }

        return ['status' => 'complete', 'processed' => $processed];
    }
}
```

## Descriptors

Separate discovery metadata from function implementation using the `#[Descriptor]` attribute:

```php
use Cline\Forrst\Attributes\Descriptor;

#[Descriptor(UserListDescriptor::class)]
class UserListFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        return User::all()->toArray();
    }
}
```

### Creating a Descriptor

```php
<?php

namespace App\Http\Functions\Descriptors;

use Cline\Forrst\Contracts\DescriptorInterface;
use Cline\Forrst\Discovery\ArgumentData;
use Cline\Forrst\Discovery\FunctionDescriptor;
use Cline\Forrst\Discovery\ResultDescriptorData;

class UserListDescriptor implements DescriptorInterface
{
    public static function create(): FunctionDescriptor
    {
        return FunctionDescriptor::make()
            ->urn('urn:acme:forrst:fn:users:list')
            ->version('1.0.0')
            ->summary('List all users')
            ->description('Retrieves a paginated list of users with optional filtering')
            ->arguments([
                ArgumentData::make('page', 'integer')
                    ->description('Page number')
                    ->default(1),
                ArgumentData::make('per_page', 'integer')
                    ->description('Items per page')
                    ->default(15),
                ArgumentData::make('status', 'string')
                    ->description('Filter by user status')
                    ->enum(['active', 'inactive', 'pending']),
            ])
            ->result(
                ResultDescriptorData::make('array')
                    ->description('Paginated list of users')
            )
            ->tags([
                TagData::make('users', 'User Management'),
            ]);
    }
}
```

### Descriptor Fluent API

```php
FunctionDescriptor::make()
    // Identity
    ->urn('urn:acme:forrst:fn:orders:create')
    ->version('2.0.0')
    ->summary('Create a new order')
    ->description('Creates an order with line items and shipping details')

    // Arguments
    ->arguments([
        ArgumentData::make('user_id', 'integer')->required(),
        ArgumentData::make('items', 'array')->required(),
        ArgumentData::make('shipping_address', 'object'),
    ])

    // Result
    ->result(ResultDescriptorData::make('object'))

    // Errors
    ->errors([
        ErrorDefinitionData::make('INSUFFICIENT_STOCK', 'Not enough stock'),
        ErrorDefinitionData::make('INVALID_ADDRESS', 'Shipping address invalid'),
    ])

    // Metadata
    ->tags([TagData::make('orders', 'Order Management')])
    ->deprecated(DeprecatedData::make('2.0.0', 'Use urn:acme:forrst:fn:orders:create-v2'))
    ->sideEffects(['creates_order', 'sends_email'])

    // Discovery
    ->discoverable(true)
    ->examples([
        ExampleData::make('Basic order', [...]),
    ])
    ->externalDocs(ExternalDocsData::make('https://docs.example.com/orders'));
```

## Error Handling

Throw exceptions for error responses:

```php
use Cline\Forrst\Exceptions\FunctionException;

class UserGetFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        $user = User::find($this->requestObject->arguments['id']);

        if (!$user) {
            throw FunctionException::notFound('User not found', [
                'user_id' => $this->requestObject->arguments['id'],
            ]);
        }

        return $user->toArray();
    }
}
```

### Built-in Exception Methods

```php
// 404 Not Found
throw FunctionException::notFound('Resource not found');

// 400 Bad Request
throw FunctionException::invalidArgument('Invalid email format');

// 403 Forbidden
throw FunctionException::forbidden('Access denied');

// 409 Conflict
throw FunctionException::conflict('Resource already exists');

// 500 Internal Error
throw FunctionException::internal('Unexpected error occurred');
```

### Custom Error Codes

```php
throw new FunctionException(
    code: 'INSUFFICIENT_BALANCE',
    message: 'User balance is insufficient',
    details: [
        'required' => 100.00,
        'available' => 25.50,
    ],
);
```

## Dependency Injection

Functions support constructor injection:

```php
class OrderCreateFunction extends AbstractFunction
{
    public function __construct(
        private PaymentGateway $payments,
        private NotificationService $notifications,
    ) {}

    public function __invoke(): array
    {
        $order = Order::create($this->requestObject->arguments);

        $this->payments->charge($order);
        $this->notifications->orderCreated($order);

        return $order->toArray();
    }
}
```

## URN Enums

Use enums for type-safe URN management:

```php
use Cline\Forrst\Functions\FunctionUrn;

enum OrderFunctions: string
{
    use FunctionUrn;

    case List = 'urn:acme:forrst:fn:orders:list';
    case Get = 'urn:acme:forrst:fn:orders:get';
    case Create = 'urn:acme:forrst:fn:orders:create';
    case Update = 'urn:acme:forrst:fn:orders:update';
    case Delete = 'urn:acme:forrst:fn:orders:delete';
}
```

Use in descriptors:

```php
FunctionDescriptor::make()
    ->urn(OrderFunctions::List)
    ->version('1.0.0');
```

## Best Practices

### Keep Functions Focused

Each function should do one thing well:

```php
// Good: Single responsibility
class UserCreateFunction extends AbstractFunction { ... }
class UserUpdateEmailFunction extends AbstractFunction { ... }
class UserResetPasswordFunction extends AbstractFunction { ... }

// Avoid: Too many responsibilities
class UserManagementFunction extends AbstractFunction { ... }
```

### Use Descriptors for Complex Functions

Separate metadata from logic for maintainability:

```php
// Clean function with business logic only
#[Descriptor(OrderCreateDescriptor::class)]
class OrderCreateFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        // Pure business logic
    }
}
```

### Validate Input Early

```php
class UserCreateFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        $validated = validator($this->requestObject->arguments, [
            'email' => 'required|email|unique:users',
            'name' => 'required|string|max:255',
            'password' => 'required|min:8',
        ])->validate();

        return User::create($validated)->toArray();
    }
}
```

## Next Steps

- **[Extensions](extensions)** - Add caching, idempotency, and other cross-cutting concerns
- **[Servers](servers)** - Configure how functions are exposed
- **[Discovery](spec/extensions/discovery)** - Understand automatic API documentation

<a id="doc-docs-extensions"></a>

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

<a id="doc-docs-clients"></a>

---
title: Clients
description: Build type-safe Forrst clients using Saloon for consuming microservices
---

Forrst clients provide a type-safe way to consume Forrst services from other Laravel applications or PHP projects.

## Installation

The Forrst package includes client capabilities built on [Saloon](https://docs.saloon.dev):

```bash
composer require cline/forrst
```

## Quick Start

### Create a Connector

```php
<?php

namespace App\Clients;

use Cline\Forrst\Requests\ForrstConnector;

class UserServiceConnector extends ForrstConnector
{
    public function __construct(
        private string $baseUrl,
        private string $apiToken,
    ) {}

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Content-Type' => 'application/json',
        ];
    }
}
```

### Create a Request

```php
<?php

namespace App\Clients\Requests;

use Cline\Forrst\Requests\ForrstRequest;

class ListUsersRequest extends ForrstRequest
{
    public function __construct(
        private int $page = 1,
        private int $perPage = 15,
    ) {}

    public function getFunction(): string
    {
        return 'urn:acme:forrst:fn:users:list';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getArguments(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }
}
```

### Send the Request

```php
use App\Clients\UserServiceConnector;
use App\Clients\Requests\ListUsersRequest;

$connector = new UserServiceConnector(
    baseUrl: config('services.user_service.url'),
    apiToken: config('services.user_service.token'),
);

$response = $connector->send(new ListUsersRequest(page: 1, perPage: 25));

// Access the result
$users = $response->result();

// Check for errors
if ($response->hasErrors()) {
    $errors = $response->errors();
}
```

## Request Building

### Basic Request

```php
class GetUserRequest extends ForrstRequest
{
    public function __construct(
        private int $userId,
    ) {}

    public function getFunction(): string
    {
        return 'urn:acme:forrst:fn:users:get';
    }

    public function getArguments(): array
    {
        return ['id' => $this->userId];
    }
}
```

### Request with Extensions

```php
class CreateOrderRequest extends ForrstRequest
{
    public function __construct(
        private array $orderData,
        private string $idempotencyKey,
    ) {}

    public function getFunction(): string
    {
        return 'urn:acme:forrst:fn:orders:create';
    }

    public function getArguments(): array
    {
        return $this->orderData;
    }

    public function getExtensions(): array
    {
        return [
            'idempotency' => [
                'key' => $this->idempotencyKey,
            ],
        ];
    }
}
```

### Request with Tracing Context

```php
class ProcessPaymentRequest extends ForrstRequest
{
    public function __construct(
        private array $paymentData,
        private string $traceId,
        private string $spanId,
    ) {}

    public function getFunction(): string
    {
        return 'urn:acme:forrst:fn:payments:process';
    }

    public function getArguments(): array
    {
        return $this->paymentData;
    }

    public function getContext(): array
    {
        return [
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
        ];
    }
}
```

## Response Handling

### Basic Response Access

```php
$response = $connector->send(new GetUserRequest(userId: 123));

// Get the result
$user = $response->result();

// Get specific fields
$userName = $response->result('name');
$userEmail = $response->result('email');

// Get the full response data
$data = $response->json();
```

### Error Handling

```php
$response = $connector->send(new CreateOrderRequest($data, $key));

if ($response->hasErrors()) {
    foreach ($response->errors() as $error) {
        logger()->error('Forrst error', [
            'code' => $error['code'],
            'message' => $error['message'],
            'details' => $error['details'] ?? null,
        ]);
    }

    throw new OrderCreationException($response->errors());
}

return $response->result();
```

### Extension Response Data

```php
$response = $connector->send(new ListUsersRequest());

// Get caching extension data
$caching = $response->extension('caching');
$etag = $caching['etag'] ?? null;
$cacheStatus = $caching['cache_status'] ?? null;

// Get rate limit data
$rateLimit = $response->extension('rate_limit');
$remaining = $rateLimit['remaining'] ?? null;
```

### Response DTO Mapping

Map responses to Data Transfer Objects:

```php
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $createdAt,
    ) {}
}
```

```php
$response = $connector->send(new GetUserRequest(userId: 123));

// Map to DTO
$user = UserData::from($response->result());

echo $user->name; // "Jane Doe"
```

### Collection Mapping

```php
$response = $connector->send(new ListUsersRequest());

// Map to collection of DTOs
$users = UserData::collect($response->result());

foreach ($users as $user) {
    echo $user->email;
}
```

## Connector Configuration

### Authentication

```php
class UserServiceConnector extends ForrstConnector
{
    protected function defaultAuth(): ?Authenticator
    {
        return new TokenAuthenticator(config('services.user.token'));
    }
}
```

### Middleware

```php
class UserServiceConnector extends ForrstConnector
{
    public function __construct()
    {
        $this->middleware()->onRequest(function (PendingRequest $request) {
            $request->headers()->add('X-Request-ID', Str::uuid());
        });

        $this->middleware()->onResponse(function (Response $response) {
            logger()->info('Forrst response', [
                'status' => $response->status(),
                'duration' => $response->getRequestTime(),
            ]);
        });
    }
}
```

### Retry Logic

```php
class UserServiceConnector extends ForrstConnector
{
    public function __construct()
    {
        $this->sender(
            new RetrySender(
                maxAttempts: 3,
                delay: 1000, // ms
                multiplier: 2,
            )
        );
    }
}
```

### Timeout Configuration

```php
class UserServiceConnector extends ForrstConnector
{
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 30,
            'connect_timeout' => 5,
        ];
    }
}
```

## Service Abstraction

Create a service class for cleaner API:

```php
<?php

namespace App\Services;

class UserService
{
    public function __construct(
        private UserServiceConnector $connector,
    ) {}

    public function list(int $page = 1, int $perPage = 15): Collection
    {
        $response = $this->connector->send(
            new ListUsersRequest($page, $perPage)
        );

        return UserData::collect($response->result());
    }

    public function find(int $id): ?UserData
    {
        $response = $this->connector->send(new GetUserRequest($id));

        if ($response->hasErrors()) {
            return null;
        }

        return UserData::from($response->result());
    }

    public function create(array $data): UserData
    {
        $response = $this->connector->send(
            new CreateUserRequest($data, Str::uuid())
        );

        if ($response->hasErrors()) {
            throw new UserCreationException($response->errors());
        }

        return UserData::from($response->result());
    }
}
```

### Service Registration

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(UserServiceConnector::class, function ($app) {
        return new UserServiceConnector(
            baseUrl: config('services.user_service.url'),
            apiToken: config('services.user_service.token'),
        );
    });

    $this->app->singleton(UserService::class);
}
```

### Usage

```php
class OrderController extends Controller
{
    public function __construct(
        private UserService $users,
    ) {}

    public function store(Request $request)
    {
        $user = $this->users->find($request->user_id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Create order...
    }
}
```

## Testing

### Mock Responses

```php
use Saloon\Laravel\Facades\Saloon;

test('lists users from service', function () {
    Saloon::fake([
        ListUsersRequest::class => MockResponse::make([
            'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
            'id' => 'test-001',
            'result' => [
                ['id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com'],
            ],
        ]),
    ]);

    $service = app(UserService::class);
    $users = $service->list();

    expect($users)->toHaveCount(1)
        ->and($users->first()->name)->toBe('Jane');
});
```

### Assert Requests

```php
test('sends correct request payload', function () {
    Saloon::fake([
        CreateUserRequest::class => MockResponse::make(['result' => [...]]),
    ]);

    $service = app(UserService::class);
    $service->create(['name' => 'John', 'email' => 'john@example.com']);

    Saloon::assertSent(function (Request $request) {
        $body = json_decode($request->body()->all(), true);

        return $body['call']['function'] === 'urn:acme:forrst:fn:users:create'
            && $body['call']['arguments']['name'] === 'John';
    });
});
```

### Error Response Testing

```php
test('handles not found error', function () {
    Saloon::fake([
        GetUserRequest::class => MockResponse::make([
            'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
            'id' => 'test-001',
            'result' => null,
            'errors' => [
                ['code' => 'NOT_FOUND', 'message' => 'User not found'],
            ],
        ]),
    ]);

    $service = app(UserService::class);
    $user = $service->find(999);

    expect($user)->toBeNull();
});
```

## Best Practices

### Centralize Configuration

```php
// config/services.php
return [
    'user_service' => [
        'url' => env('USER_SERVICE_URL'),
        'token' => env('USER_SERVICE_TOKEN'),
        'timeout' => env('USER_SERVICE_TIMEOUT', 30),
    ],
];
```

### Handle Transient Failures

```php
class ResilientConnector extends ForrstConnector
{
    public function __construct()
    {
        $this->sender(
            new RetrySender(
                maxAttempts: 3,
                delay: 500,
                multiplier: 2,
                retryOnStatusCodes: [429, 500, 502, 503, 504],
            )
        );
    }
}
```

### Circuit Breaker Pattern

```php
use Staudenmeir\LaravelMigrationViews\Facades\Schema;

class UserServiceConnector extends ForrstConnector
{
    public function send(Request $request, MockClient $mockClient = null): Response
    {
        if ($this->isCircuitOpen()) {
            throw new ServiceUnavailableException('User service circuit is open');
        }

        try {
            $response = parent::send($request, $mockClient);
            $this->recordSuccess();
            return $response;
        } catch (Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }
}
```

## Next Steps

- **[Servers](servers)** - Build Forrst servers that clients consume
- **[Functions](functions)** - Implement the functions clients call
- **[Extensions](extensions)** - Understand extension data in responses

<a id="doc-docs-testing"></a>

---
title: Testing
description: Test Forrst functions and servers with Pest and Laravel testing utilities
---

Forrst provides testing utilities to verify your functions, servers, and integrations work correctly.

## Testing Helpers

### post_forrst Helper

The `post_forrst` helper simplifies making Forrst requests in tests:

```php
use function Cline\Forrst\post_forrst;

test('lists users successfully', function () {
    $response = post_forrst('urn:acme:forrst:fn:users:list');

    $response->assertOk();
    $response->assertJsonPath('result.0.name', 'Jane Doe');
});
```

### With Arguments

```php
test('gets user by id', function () {
    $user = User::factory()->create(['name' => 'John']);

    $response = post_forrst('urn:acme:forrst:fn:users:get', ['id' => $user->id]);

    $response->assertOk();
    $response->assertJsonPath('result.name', 'John');
});
```

### With Custom Request ID

```php
test('uses custom request id', function () {
    $response = post_forrst('urn:acme:forrst:fn:users:list', [], null, 'my-test-id-123');

    $response->assertJsonPath('id', 'my-test-id-123');
});
```

### With Extensions

```php
test('request with caching extension', function () {
    $response = post_forrst('urn:acme:forrst:fn:users:list', [], [
        'caching' => ['ttl' => 300],
    ]);

    $response->assertJsonPath('extensions.caching.cache_status', 'miss');
});
```

## Testing Functions

### Basic Function Test

```php
use App\Http\Functions\UserListFunction;
use Cline\Forrst\Data\RequestObjectData;

test('returns all active users', function () {
    User::factory()->count(3)->create(['active' => true]);
    User::factory()->count(2)->create(['active' => false]);

    $function = new UserListFunction();
    $function->setRequest(RequestObjectData::from([
        'id' => 'test-001',
        'call' => [
            'function' => 'urn:acme:forrst:fn:users:list',
            'version' => '1.0.0',
            'arguments' => ['active' => true],
        ],
    ]));

    $result = $function();

    expect($result)->toHaveCount(3);
});
```

### Testing with Dependencies

```php
test('creates order with payment', function () {
    $paymentGateway = Mockery::mock(PaymentGateway::class);
    $paymentGateway->shouldReceive('charge')->once()->andReturn(true);

    $function = new OrderCreateFunction($paymentGateway);
    $function->setRequest(RequestObjectData::from([
        'id' => 'test-001',
        'call' => [
            'function' => 'urn:acme:forrst:fn:orders:create',
            'version' => '1.0.0',
            'arguments' => [
                'user_id' => 1,
                'amount' => 99.99,
            ],
        ],
    ]));

    $result = $function();

    expect($result['status'])->toBe('paid');
});
```

## Testing Servers

### Full Integration Test

```php
use Illuminate\Support\Facades\Route;
use Tests\Support\Fakes\TestServer;

beforeEach(function () {
    Route::rpc(TestServer::class);
});

test('server responds to function call', function () {
    $response = $this->postJson('/rpc', [
        'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
        'id' => 'test-001',
        'call' => [
            'function' => 'urn:acme:forrst:fn:test:hello',
            'version' => '1.0.0',
            'arguments' => [],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonPath('result.message', 'Hello, World!');
});
```

### Testing Middleware

```php
test('requires authentication', function () {
    $response = $this->postJson('/rpc', [
        'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
        'id' => 'test-001',
        'call' => [
            'function' => 'urn:acme:forrst:fn:users:list',
            'version' => '1.0.0',
            'arguments' => [],
        ],
    ]);

    $response->assertUnauthorized();
});

test('authenticated request succeeds', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/rpc', [
            'protocol' => ['name' => 'forrst', 'version' => '0.1.0'],
            'id' => 'test-001',
            'call' => [
                'function' => 'urn:acme:forrst:fn:users:list',
                'version' => '1.0.0',
                'arguments' => [],
            ],
        ]);

    $response->assertOk();
});
```

## Testing Extensions

### Caching Extension

```php
test('returns cached response on second request', function () {
    $response1 = post_forrst('urn:acme:forrst:fn:users:list', [], ['caching' => []]);
    $etag = $response1->json('extensions.caching.etag');

    $response2 = post_forrst('urn:acme:forrst:fn:users:list', [], [
        'caching' => ['if_none_match' => $etag],
    ]);

    expect($response2->json('extensions.caching.cache_status'))->toBe('hit');
});
```

### Idempotency Extension

```php
test('returns same response for duplicate idempotency key', function () {
    $key = 'test-key-' . Str::uuid();

    $response1 = post_forrst('urn:acme:forrst:fn:orders:create', [
        'user_id' => 1,
        'total' => 99.99,
    ], [
        'idempotency' => ['key' => $key],
    ]);

    $response2 = post_forrst('urn:acme:forrst:fn:orders:create', [
        'user_id' => 1,
        'total' => 199.99, // Different amount
    ], [
        'idempotency' => ['key' => $key],
    ]);

    // Should return same result despite different arguments
    expect($response1->json('result.id'))->toBe($response2->json('result.id'));
});
```

### Rate Limit Extension

```php
test('enforces rate limits', function () {
    // Make requests up to the limit
    for ($i = 0; $i < 60; $i++) {
        post_forrst('urn:acme:forrst:fn:users:list');
    }

    // Next request should be rate limited
    $response = post_forrst('urn:acme:forrst:fn:users:list');

    expect($response->json('errors.0.code'))->toBe('RATE_LIMITED');
});
```

## Testing Error Handling

### Function Errors

```php
test('returns not found error for missing user', function () {
    $response = post_forrst('urn:acme:forrst:fn:users:get', ['id' => 99999]);

    $response->assertOk(); // HTTP 200, but with Forrst error
    expect($response->json('errors.0.code'))->toBe('NOT_FOUND');
    expect($response->json('result'))->toBeNull();
});
```

### Validation Errors

```php
test('returns invalid argument error for bad input', function () {
    $response = post_forrst('urn:acme:forrst:fn:users:create', [
        'email' => 'not-an-email',
        'name' => '',
    ]);

    expect($response->json('errors.0.code'))->toBe('INVALID_ARGUMENT');
});
```

## Testing Discovery

### forrst.describe Function

```php
test('discovery returns function metadata', function () {
    $response = post_forrst('urn:cline:forrst:ext:discovery:fn:describe');

    $response->assertOk();

    $functions = collect($response->json('result.functions'));
    $usersList = $functions->firstWhere('urn', 'urn:acme:forrst:fn:users:list');

    expect($usersList)
        ->not->toBeNull()
        ->and($usersList['summary'])->not->toBeEmpty()
        ->and($usersList['version'])->toBe('1.0.0');
});
```

### Testing Function Descriptors

```php
use App\Http\Functions\Descriptors\UserListDescriptor;

test('descriptor provides correct metadata', function () {
    $descriptor = UserListDescriptor::create();

    expect($descriptor->getUrn())->toBe('urn:acme:forrst:fn:users:list');
    expect($descriptor->getVersion())->toBe('1.0.0');
    expect($descriptor->getSummary())->not->toBeEmpty();
    expect($descriptor->getArguments())->toBeArray();
});
```

## Test Organization

### Pest Describe Blocks

```php
describe('UserListFunction', function () {
    describe('Happy Paths', function () {
        test('returns all users when no filters', function () {
            User::factory()->count(5)->create();

            $response = post_forrst('urn:acme:forrst:fn:users:list');

            expect($response->json('result'))->toHaveCount(5);
        });

        test('filters by status', function () {
            User::factory()->count(3)->create(['status' => 'active']);
            User::factory()->count(2)->create(['status' => 'inactive']);

            $response = post_forrst('urn:acme:forrst:fn:users:list', ['status' => 'active']);

            expect($response->json('result'))->toHaveCount(3);
        });
    });

    describe('Sad Paths', function () {
        test('returns empty array when no users', function () {
            $response = post_forrst('urn:acme:forrst:fn:users:list');

            expect($response->json('result'))->toBeEmpty();
        });
    });

    describe('Edge Cases', function () {
        test('handles pagination at boundary', function () {
            User::factory()->count(100)->create();

            $response = post_forrst('urn:acme:forrst:fn:users:list', [
                'page' => 10,
                'per_page' => 10,
            ]);

            expect($response->json('result'))->toHaveCount(10);
        });
    });
});
```

## Fake Servers

Create fake servers for testing:

```php
<?php

namespace Tests\Support\Fakes;

use Cline\Forrst\Servers\AbstractServer;

class TestServer extends AbstractServer
{
    public function getRoutePath(): string
    {
        return '/rpc';
    }

    public function getRouteName(): string
    {
        return 'rpc';
    }

    public function functions(): array
    {
        return [
            TestHelloFunction::class,
            TestEchoFunction::class,
        ];
    }
}
```

```php
<?php

namespace Tests\Support\Fakes;

use Cline\Forrst\Functions\AbstractFunction;

class TestHelloFunction extends AbstractFunction
{
    public function getUrn(): string
    {
        return 'urn:acme:forrst:fn:test:hello';
    }

    public function __invoke(): array
    {
        return ['message' => 'Hello, World!'];
    }
}
```

## Database Testing

### With Transactions

```php
uses(RefreshDatabase::class);

test('creates user in database', function () {
    $response = post_forrst('urn:acme:forrst:fn:users:create', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});
```

### With Factories

```php
test('updates existing user', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $response = post_forrst('urn:acme:forrst:fn:users:update', [
        'id' => $user->id,
        'name' => 'New Name',
    ]);

    expect($user->fresh()->name)->toBe('New Name');
});
```

## Performance Testing

### Response Time

```php
test('responds within acceptable time', function () {
    User::factory()->count(1000)->create();

    $start = microtime(true);
    $response = post_forrst('urn:acme:forrst:fn:users:list');
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(0.5); // 500ms
});
```

### Memory Usage

```php
test('handles large result set without memory issues', function () {
    User::factory()->count(10000)->create();

    $memoryBefore = memory_get_usage();
    $response = post_forrst('urn:acme:forrst:fn:users:list', ['per_page' => 100]);
    $memoryAfter = memory_get_usage();

    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024;

    expect($memoryUsed)->toBeLessThan(50); // 50MB
});
```

## Next Steps

- **[Functions](functions)** - Build functions to test
- **[Extensions](extensions)** - Test extension behavior
- **[Clients](clients)** - Test client integrations

<a id="doc-docs-events-request-validated-priority"></a>

# RequestValidated Event Listener Priority

The `RequestValidated` event is dispatched early in the request lifecycle, making it ideal for authentication, authorization, and rate limiting. Listeners should be ordered by priority to ensure security checks occur before other processing.

## Priority Order (Highest to Lowest)

### 1. Authentication (Priority: 100)

Verify client credentials and establish identity.

**Purpose:** Ensure the request comes from a known, authenticated client before any other processing occurs.

**Example:**

```php
class AuthenticationListener
{
    public function handle(RequestValidated $event): void
    {
        $authHeader = $event->request->metadata['Authorization'] ?? null;

        if (!$authHeader) {
            $event->rejectUnauthorized('Authentication required');
            return;
        }

        $user = $this->authenticateUser($authHeader);
        if (!$user) {
            $event->rejectUnauthorized('Invalid credentials');
            return;
        }

        // Store authenticated user in request context
        $event->request->setAuthenticatedUser($user);
    }
}
```

### 2. Authorization (Priority: 90)

Check if authenticated client has permission for the requested function.

**Purpose:** Verify that the authenticated user has sufficient permissions to execute the requested function.

**Example:**

```php
class AuthorizationListener
{
    public function handle(RequestValidated $event): void
    {
        $user = $event->request->getAuthenticatedUser();
        $function = $event->request->function;

        if (!$this->canAccess($user, $function)) {
            $event->rejectRequest(
                errorCode: ErrorCode::Forbidden,
                message: "You do not have permission to access function: {$function}",
            );
        }
    }
}
```

### 3. Rate Limiting (Priority: 80)

Prevent abuse by limiting request rate per client/function.

**Purpose:** Protect the server from abuse by enforcing rate limits on requests.

**Example:**

```php
class RateLimitListener
{
    public function handle(RequestValidated $event): void
    {
        $user = $event->request->getAuthenticatedUser();
        $key = "rate_limit:{$user->id}:{$event->request->function}";

        if ($this->rateLimiter->tooManyAttempts($key, $maxAttempts = 60)) {
            $retryAfter = $this->rateLimiter->availableIn($key);
            $event->rejectRateLimited($retryAfter);
            return;
        }

        $this->rateLimiter->hit($key, $decayMinutes = 1);
    }
}
```

### 4. Request Sanitization (Priority: 70)

Clean and normalize request data.

**Purpose:** Sanitize potentially dangerous input and normalize data formats before processing.

**Example:**

```php
class RequestSanitizationListener
{
    public function handle(RequestValidated $event): void
    {
        // Sanitize string arguments
        $sanitizedArgs = array_map(
            fn($arg) => is_string($arg) ? $this->sanitize($arg) : $arg,
            $event->request->arguments ?? []
        );

        // Update request with sanitized arguments
        $event->request->arguments = $sanitizedArgs;
    }
}
```

### 5. Custom Validation (Priority: 50)

Application-specific validation rules.

**Purpose:** Apply domain-specific validation logic that goes beyond standard protocol validation.

**Example:**

```php
class CustomValidationListener
{
    public function handle(RequestValidated $event): void
    {
        // Validate business rules
        if (!$this->validateBusinessRules($event->request)) {
            $event->rejectRequest(
                errorCode: ErrorCode::InvalidArguments,
                message: 'Business rule validation failed',
                metadata: ['validation_errors' => $this->getValidationErrors()],
            );
        }
    }
}
```

### 6. Logging/Metrics (Priority: 10)

Record request for audit trail or metrics.

**Purpose:** Log incoming requests for debugging, monitoring, and compliance purposes.

**Example:**

```php
class RequestLoggingListener
{
    public function handle(RequestValidated $event): void
    {
        Log::info('Request validated', [
            'function' => $event->request->function,
            'user_id' => $event->request->getAuthenticatedUser()?->id,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
```

## Example Configuration

### Laravel EventServiceProvider

```php
protected $listen = [
    RequestValidated::class => [
        AuthenticationListener::class . '@handle:100',
        AuthorizationListener::class . '@handle:90',
        RateLimitListener::class . '@handle:80',
        RequestSanitizationListener::class . '@handle:70',
        CustomValidationListener::class . '@handle:50',
        RequestLoggingListener::class . '@handle:10',
    ],
];
```

### Symfony Event Configuration

```yaml
services:
    App\EventListener\AuthenticationListener:
        tags:
            - { name: kernel.event_listener, event: forrst.request_validated, priority: 100 }

    App\EventListener\AuthorizationListener:
        tags:
            - { name: kernel.event_listener, event: forrst.request_validated, priority: 90 }

    App\EventListener\RateLimitListener:
        tags:
            - { name: kernel.event_listener, event: forrst.request_validated, priority: 80 }

    App\EventListener\RequestSanitizationListener:
        tags:
            - { name: kernel.event_listener, event: forrst.request_validated, priority: 70 }

    App\EventListener\CustomValidationListener:
        tags:
            - { name: kernel.event_listener, event: forrst.request_validated, priority: 50 }

    App\EventListener\RequestLoggingListener:
        tags:
            - { name: kernel.event_listener, event: forrst.request_validated, priority: 10 }
```

## Best Practices

1. **Authentication First:** Always authenticate before performing any other checks to establish the request's identity.

2. **Authorization Second:** Only check permissions after authentication is confirmed to avoid information disclosure.

3. **Rate Limiting Third:** Apply rate limits after authentication/authorization to allow for user-specific limits.

4. **Fail Fast:** Use the rejection helper methods to stop processing immediately when validation fails.

5. **Consistent Error Responses:** Use the provided rejection helpers (`rejectUnauthorized()`, `rejectRateLimited()`, etc.) to ensure consistent error formatting.

6. **Minimal Processing:** Keep listener logic minimal and focused. Expensive operations should happen in later lifecycle stages.

7. **Logging Last:** Log requests after all other processing to capture the final decision (accepted/rejected).

## Security Considerations

- **Never skip authentication:** Even for "public" functions, establish identity (anonymous user) before processing.

- **Validate permissions:** Always verify the authenticated user has permission for the specific function being called.

- **Rate limit aggressively:** Early-stage rate limiting prevents resource exhaustion from malicious actors.

- **Sanitize input:** Clean user input before it reaches function execution to prevent injection attacks.

- **Audit trail:** Log all requests, especially rejected ones, for security monitoring and incident response.

<a id="doc-docs-naming-conventions"></a>

---
title: Function Naming Conventions
description: Comprehensive guide to function identifier formats with recommendations based on scale and requirements
---

This guide documents function naming approaches in order of recommendation, with clear reasoning for when to use each pattern.

## Quick Decision Matrix

| Your Situation | Recommended Approach |
|----------------|---------------------|
| **Forrst projects** | [URN Format](#4-urn-format) ⭐ |
| Single vendor, <50 functions | [Simple Dotted](#1-simple-dotted) |
| Single vendor, 50-200 functions | [Service-Grouped Dotted](#2-service-grouped-dotted) |
| Multi-vendor OR >200 functions | [Vendor-Prefixed](#3-vendor-prefixed) |
| Enterprise/compliance requirements | [URN Format](#4-urn-format) |
| Protobuf/schema-first design | [gRPC-Style](#5-grpc-style) |
| AWS ecosystem integration | [AWS-Style](#6-aws-style) |
| Azure ecosystem integration | [Azure-Style](#7-azure-style) |
| Blockchain/Ethereum APIs | [OpenRPC-Style](#8-openrpc-style) |
| AI/LLM tool calling | [MCP-Style](#9-mcp-style) |
| Frontend-driven, type-safe APIs | [GraphQL-Style](#10-graphql-style) |

---

## Approaches (Ranked by Recommendation)

### 1. Simple Dotted

**Best for:** Small projects, single vendor, <50 functions

```
Format: {service}.{function}
```

**Examples:**
```
orders.create
orders.get
users.authenticate
payments.process
```

**Pros:**
- Minimal verbosity
- Familiar to JSON-RPC users
- Easy to type and read
- No configuration required

**Cons:**
- No vendor isolation
- No versioning strategy
- Namespace collisions in multi-vendor environments

**When to use:**
- Internal tools and services
- Single-team projects
- Rapid prototyping
- When simplicity trumps extensibility

---

### 2. Service-Grouped Dotted

**Best for:** Medium projects, single vendor, 50-200 functions

```
Format: {domain}.{service}.{function}
```

**Examples:**
```
commerce.orders.create
commerce.orders.get
commerce.inventory.check
identity.users.authenticate
billing.payments.process
```

**Pros:**
- Logical grouping by domain
- Scales to hundreds of functions
- Clear organizational hierarchy
- Still relatively concise

**Cons:**
- No vendor attribution
- No explicit versioning
- Requires domain taxonomy planning

**When to use:**
- Growing monoliths
- Microservices within a single organization
- When you need better organization but not multi-vendor support

---

### 3. Vendor-Prefixed

**Best for:** Multi-vendor environments, >200 functions, API marketplaces

```
Format: {vendor}.{service}/{function}
```

**Examples:**
```
acme.orders/create
acme.orders/get
acme.orders/cancel
stripe.payments/charge
cline.discovery/describe
```

**Pros:**
- Clear vendor ownership
- Scales to thousands of functions
- The `/` separator makes parsing unambiguous
- Service grouping within vendor namespace
- Concise yet fully qualified

**Cons:**
- More verbose than simple dotted
- Requires vendor registry/coordination

**When to use:**
- Multi-vendor platforms
- Public APIs with external consumers
- API marketplaces and plugin systems
- When clear ownership attribution matters

**Note:** Versioning is handled at the protocol level, not in the identifier. Forrst supports native function versioning, keeping identifiers stable across versions.

---

### 4. URN Format

**Best for:** Forrst projects ⭐, enterprise environments, systems with extensions/plugins

```
Format: urn:{vendor}:{service}:{type}:{name}
```

**Examples:**
```
# Vendor functions
urn:acme:orders:fn:create
urn:acme:logistics:fn:list-shipments

# Extension functions
urn:forrst:ext:discovery:fn:describe
urn:forrst:ext:deprecation:fn:list-deprecated

# System functions
urn:forrst:system:fn:ping
```

**Pros:**
- Self-documenting structure with semantic segments
- Type distinction (`fn`, `ext`, `system`) enables routing/permissions
- Clear ownership attribution (vendor segment)
- Extension clarity (which extension provides what)
- Wildcard permission patterns (`urn:vendor:*`)
- Written once, referenced many times (verbosity cost is minimal)

**Cons:**
- Most verbose option
- Requires understanding URN structure

**When to use:**
- **Forrst projects** (recommended default)
- Systems with extensions/plugins requiring type distinction
- Multi-vendor platforms needing clear ownership
- When debugging clarity matters (logs, traces, errors)
- Permission systems using prefix matching

---

### 5. gRPC-Style

**Best for:** Protobuf/schema-first projects, strongly-typed environments

```
Format: /{package}.{Service}/{Method}
```

**Examples:**
```
/acme.orders.v1.OrderService/CreateOrder
/acme.orders.v1.OrderService/GetOrder
/acme.payments.v1.PaymentService/ProcessPayment
```

**Pros:**
- Industry-proven at massive scale (Google, etc.)
- Tight integration with Protobuf schemas
- Service grouping built-in
- Clear versioning via package
- Excellent tooling ecosystem

**Cons:**
- Requires Protobuf commitment
- Very verbose for simple use cases
- Service suffix (`Service`) often redundant

**When to use:**
- Already using Protobuf/gRPC
- Need strong typing and schema validation
- High-performance binary serialization required
- Polyglot environments with code generation

---

### 6. AWS-Style

**Best for:** AWS ecosystem integration, IAM-like permission systems

```
Format: {service}:{Action}
```

**Examples:**
```
s3:GetObject
s3:PutObject
s3:DeleteObject
dynamodb:GetItem
dynamodb:PutItem
lambda:InvokeFunction
ec2:StartInstances
```

**Pros:**
- Instantly familiar to AWS users
- Clean service:action separation
- Works well with permission/policy systems
- PascalCase actions are self-documenting
- Proven at AWS scale (thousands of actions)

**Cons:**
- No vendor prefix (assumes single provider)
- No explicit versioning
- PascalCase may conflict with kebab-case conventions
- Colon separator can conflict with URN schemes

**When to use:**
- Building AWS-integrated services
- IAM-style permission systems
- When your users already think in AWS terms
- Action-centric (vs resource-centric) APIs

**Real-world patterns from AWS:**
```
# Pattern: service:VerbNoun
s3:GetObject
s3:ListBuckets
iam:CreateUser
iam:AttachRolePolicy

# Pattern: service:VerbNounProperty
ec2:DescribeInstanceStatus
rds:ModifyDBClusterEndpoint
```

---

### 7. Azure-Style

**Best for:** Azure ecosystem integration, resource-centric APIs, RBAC systems

```
Format: {Provider}/{ResourceType}/{Action}
```

**Examples:**
```
Microsoft.Storage/storageAccounts/read
Microsoft.Storage/storageAccounts/write
Microsoft.Storage/storageAccounts/delete
Microsoft.Compute/virtualMachines/start
Microsoft.Compute/virtualMachines/restart
Microsoft.Web/sites/functions/read
```

**Pros:**
- Resource hierarchy is explicit
- Provider namespacing (multi-vendor ready)
- Maps directly to REST resource paths
- Built for RBAC permission systems
- Clear read/write/delete/action taxonomy

**Cons:**
- Very verbose
- Deep nesting can get unwieldy
- Provider prefix often redundant
- Slash separator conflicts with URL paths

**When to use:**
- Building Azure-integrated services
- Resource-centric (vs action-centric) APIs
- Hierarchical permission systems
- When resources have clear parent/child relationships

**Real-world patterns from Azure:**
```
# Pattern: Provider/Resource/action
Microsoft.Storage/storageAccounts/read
Microsoft.Storage/storageAccounts/listKeys/action

# Pattern: Provider/Resource/SubResource/action
Microsoft.Storage/storageAccounts/blobServices/containers/read
Microsoft.Web/sites/config/appsettings/read

# Wildcards for permissions
Microsoft.Storage/storageAccounts/*
Microsoft.Compute/*/read
```

**Azure vs AWS comparison:**
```
# AWS (action-centric)
s3:GetObject
s3:PutObject

# Azure (resource-centric)
Microsoft.Storage/storageAccounts/blobServices/containers/blobs/read
Microsoft.Storage/storageAccounts/blobServices/containers/blobs/write
```

---

### 8. OpenRPC-Style

**Best for:** Blockchain/Ethereum APIs, JSON-RPC with underscore conventions

```
Format: {namespace}_{method}
```

**Examples:**
```
eth_getBalance
eth_sendTransaction
eth_getBlockByNumber
eth_estimateGas
net_version
net_peerCount
web3_clientVersion
personal_sign
debug_traceTransaction
```

**Pros:**
- Standard in Ethereum/blockchain ecosystem
- Simple flat namespace
- Underscore separator is unambiguous
- Easy to parse and validate
- Well-documented in OpenRPC spec

**Cons:**
- Underscore conflicts with snake_case function names
- Limited hierarchy (only one level)
- No versioning strategy
- Namespace feels like a prefix, not a category

**When to use:**
- Building blockchain/Ethereum-compatible APIs
- JSON-RPC APIs following OpenRPC specification
- When interoperability with Web3 tooling matters
- Simple APIs with flat structure

**Real-world patterns:**
```
# Core Ethereum methods
eth_accounts
eth_blockNumber
eth_call
eth_chainId
eth_getCode

# Namespaces indicate subsystem
eth_*        → Core Ethereum
net_*        → Network info
web3_*       → Web3 utilities
personal_*   → Account management
debug_*      → Debugging tools
trace_*      → Transaction tracing
```

---

### 9. MCP-Style

**Best for:** AI tool integration, LLM function calling, multi-provider tool registries

```
Format: {vendor}__{tool} or {tool}
```

**Examples:**
```
# Vendor-prefixed (multi-provider)
github__create_issue
github__list_repos
github__create_pull_request
slack__send_message
slack__list_channels
linear__create_issue

# Simple (single-provider)
create_file
read_file
search_code
run_terminal_command
```

**Pros:**
- Designed for AI/LLM tool calling
- Double underscore is unambiguous separator
- Vendor prefix enables multi-provider environments
- Snake_case matches Python conventions (common in AI)
- Simple tools can omit vendor prefix

**Cons:**
- Double underscore looks unusual
- No versioning strategy
- No service/domain grouping
- Relatively new standard (less proven)

**When to use:**
- Building MCP (Model Context Protocol) servers
- AI assistant tool integrations
- LLM function calling systems
- Multi-provider tool registries

**Real-world patterns from MCP:**
```
# File operations
read_file
write_file
list_directory

# With vendor prefix
github__search_repositories
github__get_file_contents
github__create_or_update_file

# Naming convention
- Use snake_case for tool names
- Verb_noun pattern: create_issue, list_repos
- Vendor prefix when multiple providers possible
```

---

### 10. GraphQL-Style

**Best for:** Query languages, frontend-driven APIs, type-safe schemas

```
Format: {verbNoun} (camelCase)
```

**Examples:**
```
# Queries (read operations)
user
users
orderById
ordersByCustomer
searchProducts

# Mutations (write operations)
createUser
updateUser
deleteUser
createOrder
cancelOrder
processPayment

# Subscriptions (real-time)
onOrderCreated
onPaymentProcessed
onUserUpdated
```

**Pros:**
- Extremely concise
- Self-documenting with verb prefixes
- Type system provides context (Query vs Mutation)
- Frontend-developer friendly
- Strong tooling ecosystem (Apollo, Relay)

**Cons:**
- No namespace/vendor isolation
- Relies on schema context for meaning
- Name collisions in large schemas
- Not suitable for flat RPC (needs GraphQL schema)

**When to use:**
- Building GraphQL APIs
- Frontend-driven API design
- When schema provides the namespace context
- Type-safe, introspectable APIs

**Naming conventions:**
```
# Queries - noun or getNoun
user                    # Get single by ID
users                   # Get list
userByEmail            # Get by specific field

# Mutations - verbNoun
createUser
updateUser
deleteUser
assignUserRole
resetPassword

# Subscriptions - onNounVerbed
onUserCreated
onOrderUpdated
onPaymentFailed

# Input types
CreateUserInput
UpdateOrderInput
```

**GraphQL vs RPC comparison:**
```
# GraphQL (schema-contextual)
query { user(id: "123") { name } }
mutation { createUser(input: {...}) { id } }

# RPC equivalent
users.get(id: "123")
users.create(input: {...})
```

---

## Industry Standards Reference

| Standard | Format | Primary Use Case |
|----------|--------|------------------|
| JSON-RPC 2.0 | `namespace.method` | Simple RPC |
| gRPC | `/package.Service/Method` | High-performance microservices |
| OpenRPC | `namespace_method` | Ethereum/blockchain APIs |
| MCP | `vendor__tool` | AI tool integration |
| AWS IAM | `service:Action` | Cloud permissions |
| Azure RBAC | `Provider/Type/Action` | Cloud permissions |
| GraphQL | `verbNoun` | Query languages |

---

## Migration Paths

### From Simple → Service-Grouped

```
# Before
orders.create
users.get

# After
commerce.orders.create
identity.users.get
```

**Strategy:** Prefix with domain, update clients, deprecate old names.

### From Dotted → Vendor-Prefixed

```
# Before
commerce.orders.create

# After
acme.orders/create
```

**Strategy:**
1. Support both formats during transition
2. Log usage of deprecated format
3. Set deprecation deadline
4. Remove old format support

### From Any → URN

```
# Before
acme.orders/create

# After
urn:acme:forrst:fn:orders:create
```

**Strategy:** Usually unnecessary. Only migrate if compliance requires it.

---

## Forrst Recommendations

For Forrst projects, we recommend **URN format** due to its explicit structure that distinguishes between vendor functions, system extensions, and extension functions.

### Recommended Format: URN

URNs provide semantic structure that carries meaning beyond just identification:

```
urn:{vendor}:{service}:{type}:{function}
```

### URN Structures

#### 1. Vendor Functions

User-defined functions provided by a vendor/service:

```
Format:  urn:{vendor}:{service}:fn:{function}
```

**Examples:**
```
urn:acme:logistics:fn:create-shipment
urn:acme:logistics:fn:list-events
urn:acme:postal:fn:validate-address
urn:acme:locations:fn:search-locations
urn:stripe:payments:fn:create-charge
urn:acme:orders:fn:cancel-order
```

#### 2. Extension Functions

Functions provided by Forrst protocol extensions:

```
Format:  urn:forrst:ext:{extension}:fn:{function}
```

**Examples:**
```
urn:forrst:ext:discovery:fn:describe
urn:forrst:ext:discovery:fn:capabilities
urn:forrst:ext:deprecation:fn:list-deprecated
urn:forrst:ext:diagnostics:fn:health-check
urn:forrst:ext:tracing:fn:get-trace
urn:forrst:ext:caching:fn:invalidate
urn:forrst:ext:rate-limit:fn:get-limits
```

#### 3. System Functions

Core Forrst protocol functions (not extensions):

```
Format:  urn:forrst:system:fn:{function}
```

**Examples:**
```
urn:forrst:system:fn:ping
urn:forrst:system:fn:version
urn:forrst:system:fn:shutdown
```

### URN Segment Reference

| Segment | Purpose | Examples |
|---------|---------|----------|
| `{vendor}` | Who owns/provides this | `acme`, `stripe`, `forrst` |
| `{service}` | Domain/service grouping | `orders`, `payments`, `inventory`, `logistics` |
| `{type}` | What kind of identifier | `fn` (function), `ext` (extension), `system` |
| `{extension}` | Which extension (for ext type) | `discovery`, `deprecation`, `tracing` |
| `{function}` | The function name | `create-shipment`, `describe` |

### Why URNs for Forrst

The explicit structure enables:

```php
// Instantly identify what you're dealing with
$urn = 'urn:forrst:ext:deprecation:fn:list-deprecated';

// Parse and route based on structure
if (str_starts_with($urn, 'urn:forrst:ext:')) {
    // System extension - special handling
} elseif (str_starts_with($urn, 'urn:forrst:system:')) {
    // Core system function
} else {
    // Vendor function - route to appropriate handler
}
```

**Debugging clarity:**
```
[ERROR] urn:acme:logistics:fn:create-shipment failed    → User code issue
[ERROR] urn:forrst:ext:discovery:fn:describe failed      → System extension issue
```

**Permission patterns:**
```
allow: urn:acme:*             # All acme functions
allow: urn:forrst:ext:*       # All extensions
deny:  urn:forrst:system:*    # Block system functions
```

### Reserved Namespaces

```
urn:forrst:*     → Forrst protocol (extensions & system)
urn:cline:*      → Cline official services
```

All other vendor namespaces are available for user registration.

### Function Naming Rules

- Use `kebab-case` for function names: `create-order`, not `createOrder`
- Use singular nouns for resources: `order`, not `orders`
- Use verb-noun pattern: `create-order`, `get-user`, `process-payment`

### Versioning

Versioning is handled at the protocol level, not in URNs. This keeps identifiers stable across versions and allows the protocol to manage version negotiation, deprecation, and compatibility.

---

## Examples at Scale

For a platform with 400 functions across 20 services:

```
# Payment processing vendor
urn:stripe:charges:fn:create
urn:stripe:charges:fn:capture
urn:stripe:charges:fn:refund
urn:stripe:customers:fn:create
urn:stripe:customers:fn:update

# Shipping vendor
urn:acme:shipments:fn:create
urn:acme:shipments:fn:track
urn:acme:rates:fn:calculate

# Internal services
urn:acme:orders:fn:create
urn:acme:orders:fn:cancel
urn:acme:inventory:fn:reserve
urn:acme:inventory:fn:release

# Forrst extensions
urn:forrst:ext:discovery:fn:describe
urn:forrst:ext:discovery:fn:capabilities
urn:forrst:ext:deprecation:fn:list-deprecated
urn:forrst:ext:diagnostics:fn:health-check

# Forrst system
urn:forrst:system:fn:ping
urn:forrst:system:fn:version
```

This provides:
- **Clear ownership** — vendor segment identifies who to contact
- **Type distinction** — `fn` vs `ext` vs `system` immediately visible
- **Service grouping** — related functions organized together
- **Extension clarity** — which extension provides what function
- **Stable identifiers** — versioning handled at protocol level
- **Permission patterns** — wildcard matching on URN prefixes
