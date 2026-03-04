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
