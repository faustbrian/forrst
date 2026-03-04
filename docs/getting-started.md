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
