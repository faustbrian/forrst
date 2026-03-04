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
