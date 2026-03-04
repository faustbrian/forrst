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
