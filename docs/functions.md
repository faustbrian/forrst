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
