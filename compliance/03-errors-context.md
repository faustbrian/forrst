# Errors, Context & Rate Limiting Compliance

## Summary
- Compliant: 42 items
- Issues: 5 items
- Missing tests: 3 items

---

## errors.md Compliance

### ✅ Compliant

#### Error Object Structure
- **ErrorData class** (`/Users/brian/Developer/cline/vend/src/Data/ErrorData.php`): Fully implements all required fields
  - `code` (string, required) - Line 28
  - `message` (string, required) - Line 46
  - `retryable` (boolean, required) - Line 33
  - `source` (optional SourceData) - Line 48
  - `details` (optional array) - Line 49

#### Error Code Enum
- **ErrorCode enum** (`/Users/brian/Developer/cline/vend/src/Enums/ErrorCode.php`): All standard error codes present
  - Protocol errors: `PARSE_ERROR`, `INVALID_REQUEST`, `INVALID_PROTOCOL_VERSION` (lines 23-25)
  - Function errors: `FUNCTION_NOT_FOUND`, `VERSION_NOT_FOUND`, `FUNCTION_DISABLED`, `INVALID_ARGUMENTS`, `SCHEMA_VALIDATION_FAILED`, `EXTENSION_NOT_SUPPORTED` (lines 28-33)
  - Auth errors: `UNAUTHORIZED`, `FORBIDDEN` (lines 36-37)
  - Resource errors: `NOT_FOUND`, `CONFLICT`, `GONE` (lines 40-42)
  - Operational errors: `DEADLINE_EXCEEDED`, `RATE_LIMITED`, `INTERNAL_ERROR`, `UNAVAILABLE`, `DEPENDENCY_ERROR` (lines 45-49)
  - Idempotency errors: `IDEMPOTENCY_CONFLICT`, `IDEMPOTENCY_PROCESSING` (lines 52-53)
  - Async errors: `ASYNC_OPERATION_NOT_FOUND`, `ASYNC_OPERATION_FAILED`, `ASYNC_CANNOT_CANCEL` (lines 56-58)
  - Batch errors: `BATCH_FAILED`, `BATCH_TOO_LARGE`, `BATCH_TIMEOUT` (lines 61-63)
- All error codes use SCREAMING_SNAKE_CASE format as required

#### Retryable Logic
- **ErrorCode.isRetryable()** method (lines 68-81): Correctly identifies retryable errors
  - `FUNCTION_DISABLED`, `DEADLINE_EXCEEDED`, `RATE_LIMITED`, `INTERNAL_ERROR`, `UNAVAILABLE`, `DEPENDENCY_ERROR`, `IDEMPOTENCY_PROCESSING`, `BATCH_TIMEOUT` return true
  - All other errors return false (non-retryable)

#### Source Object
- **SourceData class** (`/Users/brian/Developer/cline/vend/src/Data/Errors/SourceData.php`): Fully compliant
  - `pointer` (optional string) for JSON Pointer (RFC 6901) - Line 37
  - `position` (optional int) for byte offset - Line 38
  - Enforces "pointer OR position, not both" rule via constructor design
  - Implements factory methods: `pointer()` (line 44), `position()` (line 52)
  - toArray() method excludes null fields (lines 62-75)

#### Error Response Structure
- **ResponseData class** (`/Users/brian/Developer/cline/vend/src/Data/ResponseData.php`):
  - Supports multiple errors via `errors` array (line 40)
  - Properly serializes errors in toArray() (lines 304-309)
  - Sets `result` to null when errors present (line 305)

#### Exception Mapping
- **AbstractRequestException** (`/Users/brian/Developer/cline/vend/src/Exceptions/AbstractRequestException.php`):
  - All exceptions extend this base class
  - Wraps ErrorData (line 37)
  - Provides `toError()` method (line 119)
  - Implements `isRetryable()` (line 89)
  - Maps to HTTP status codes via `getStatusCode()` (line 99)

#### HTTP Status Code Mapping
- **ErrorCode.toStatusCode()** method (lines 128-159): Correct mappings
  - 400: `PARSE_ERROR`, `INVALID_REQUEST`, `INVALID_PROTOCOL_VERSION`, `INVALID_ARGUMENTS`, `EXTENSION_NOT_SUPPORTED`, `ASYNC_CANNOT_CANCEL`, `BATCH_FAILED`
  - 401: `UNAUTHORIZED`
  - 403: `FORBIDDEN`
  - 404: `FUNCTION_NOT_FOUND`, `VERSION_NOT_FOUND`, `NOT_FOUND`, `ASYNC_OPERATION_NOT_FOUND`
  - 409: `CONFLICT`, `IDEMPOTENCY_CONFLICT`, `IDEMPOTENCY_PROCESSING`
  - 410: `GONE`
  - 413: `BATCH_TOO_LARGE`
  - 422: `SCHEMA_VALIDATION_FAILED`
  - 429: `RATE_LIMITED`
  - 500: `INTERNAL_ERROR`, `ASYNC_OPERATION_FAILED`
  - 502: `DEPENDENCY_ERROR`
  - 503: `FUNCTION_DISABLED`, `UNAVAILABLE`
  - 504: `DEADLINE_EXCEEDED`, `BATCH_TIMEOUT`

#### Custom Error Codes
- **ErrorData** supports custom string codes (line 44: `ErrorCode|string`)
- Auto-determines retryable from ErrorCode enum when available (lines 54-59)
- Falls back to false for custom codes

### ❌ Issues

#### 1. Single vs Multiple Error Format Not Enforced
**Location**: `/Users/brian/Developer/cline/vend/src/Data/ResponseData.php`

**Issue**: Documentation states "A response MUST NOT contain both `error` and `errors`" and supports both singular `error` and plural `errors` formats, but implementation only supports `errors` (plural) format.

**Documentation Requirement** (errors.md lines 46-96):
- Single error should use `"error": { ... }` (singular)
- Multiple errors should use `"errors": [ ... ]` (plural)

**Current Implementation**:
- ResponseData always uses `errors` array field (line 40)
- toArray() always serializes as `errors` array (line 306)
- No support for single `error` field

**Exception**: FunctionErrorData does support single `error` field (line 36 in `/Users/brian/Developer/cline/vend/src/Data/FunctionErrorData.php`), but this is a separate class not used by the main ResponseData.

**Recommendation**: Either:
1. Update ResponseData to serialize single-element errors array as `error` (singular), OR
2. Update documentation to reflect that Forrst implementation always uses `errors` (plural)

#### 2. Missing Rate Limit Metadata Structure
**Location**: Missing implementation

**Issue**: Documentation specifies detailed rate limit metadata structure that should be included in response `meta`, but no implementation found.

**Documentation Requirement** (rate-limiting.md lines 22-50):
```json
"meta": {
  "rate_limit": {
    "limit": 1000,
    "used": 153,
    "remaining": 847,
    "window": { "value": 1, "unit": "minute" },
    "resets_in": { "value": 45, "unit": "second" }
  }
}
```

**Current State**:
- ResponseData supports generic `meta` field (line 42)
- TooManyRequestsException includes rate limit details in error details (not meta)
- No structured rate limit metadata builder/formatter

**Recommendation**: Create RateLimitMetaData class and integrate into response metadata

#### 3. Rate Limit Error Details Structure Inconsistent
**Location**: `/Users/brian/Developer/cline/vend/src/Exceptions/TooManyRequestsException.php`

**Issue**: Rate limit error details don't match documentation structure.

**Documentation Requirement** (rate-limiting.md lines 85-92):
```json
"details": {
  "limit": 1000,
  "used": 1000,
  "window": { "value": 1, "unit": "minute" },
  "retry_after": { "value": 45, "unit": "second" }
}
```

**Current Implementation** (lines 42-48):
```php
details: [
    [
        'status' => '429',
        'title' => 'Too Many Requests',
        'detail' => $detail ?? '...',
    ],
]
```

**Recommendation**: Update TooManyRequestsException to use standardized details structure with `limit`, `used`, `window`, and `retry_after` fields

#### 4. Multiple Errors Array Validation Missing
**Location**: `/Users/brian/Developer/cline/vend/src/Data/ResponseData.php`

**Issue**: Documentation states "When using `errors`, the array MUST contain at least one error" but no validation enforces this.

**Documentation Requirement** (errors.md line 93): "When using `errors`, the array MUST contain at least one error"

**Current Implementation**: Constructor accepts nullable array (line 40), toArray() checks for non-empty (line 304) but doesn't throw on empty array

**Recommendation**: Add validation in constructor or factory methods to ensure errors array is never empty when provided

#### 5. Context `caller` Field Not Auto-Updated
**Location**: `/Users/brian/Developer/cline/vend/src/Data/RequestObjectData.php`

**Issue**: Documentation requires servers to update `caller` field for downstream calls, but no automatic mechanism exists.

**Documentation Requirement** (context.md lines 72-86):
- Servers MUST update `caller` to current service name for downstream calls
- Example shows `api-gateway` → `order-service` propagation

**Current Implementation**:
- RequestObjectData stores context as generic array (line 40)
- No helper methods to create downstream context with updated caller
- Manual implementation required by each service

**Recommendation**: Add `RequestObjectData::forDownstream(string $serviceName)` method that clones request with updated caller

### 🔲 Missing Tests

#### 1. Single Error vs Multiple Errors Format Tests
**Missing Coverage**:
- Test that verifies single error uses `error` field (singular)
- Test that verifies multiple errors use `errors` field (plural)
- Test that response never contains both `error` and `errors`
- Test that empty errors array is rejected

**Current Coverage**: ResponseDataTest only tests `errors` (plural) format

**Recommendation**: Add tests in `/Users/brian/Developer/cline/vend/tests/Unit/Data/ResponseDataTest.php`

#### 2. Rate Limit Metadata Format Tests
**Missing Coverage**:
- Test rate_limit metadata structure in successful responses
- Test rate_limit metadata in rate limited error responses
- Test multiple rate limit scopes (global, service, function, user)
- Test rate limit metadata serialization

**Current Coverage**: TooManyRequestsExceptionTest exists but doesn't test metadata structure

**Recommendation**: Create `/Users/brian/Developer/cline/vend/tests/Unit/RateLimitMetadataTest.php`

#### 3. Context Propagation Integration Tests
**Missing Coverage**:
- Test that context fields (except caller) propagate unchanged
- Test that caller field updates correctly in downstream calls
- Test missing context is handled gracefully (server continues)
- Test custom context fields (tenant_id, user_id, etc.) propagate

**Current Coverage**: RequestObjectDataTest tests context data access (line 350) but not propagation behavior

**Recommendation**: Add integration tests in `/Users/brian/Developer/cline/vend/tests/Integration/ContextPropagationTest.php`

---

## context.md Compliance

### ✅ Compliant

#### Context Object Structure
- **RequestObjectData** (`/Users/brian/Developer/cline/vend/src/Data/RequestObjectData.php`):
  - Supports optional `context` field (line 40)
  - Accepts any custom fields via array type
  - Includes `caller` in documentation (line 32)

#### Context Access Methods
- **RequestObjectData.getContext()** (lines 139-146): Supports dot notation access to context fields
- Preserves context in toArray() serialization (lines 289-291)

#### Custom Context Fields
- Implementation supports arbitrary custom fields (tenant_id, user_id, feature_flags, etc.)
- No enforcement of specific fields (allows flexibility per documentation)

#### Context in Request/Response Flow
- RequestObjectData parses context from incoming requests (line 260)
- Context included in serialized requests (lines 289-291)

### ✅ Already Covered Above
- Issue #5: Context caller field propagation

---

## rate-limiting.md Compliance

### ✅ Compliant

#### Rate Limited Error Code
- **ErrorCode.RateLimited** exists (line 46 in ErrorCode.php)
- Correctly marked as retryable (line 73 in isRetryable())
- Maps to HTTP 429 (line 146 in toStatusCode())

#### TooManyRequestsException
- **TooManyRequestsException** (`/Users/brian/Developer/cline/vend/src/Exceptions/TooManyRequestsException.php`):
  - Extends AbstractRequestException
  - Uses RATE_LIMITED error code (line 42)
  - Returns HTTP 429 status code (line 59)
  - Marked as retryable (inherited from ErrorCode)

#### Response Meta Support
- **ResponseData** supports meta field (line 42)
- Meta included in serialized responses (lines 321-323)

### ❌ Already Covered Above
- Issue #2: Missing rate limit metadata structure
- Issue #3: Rate limit error details structure inconsistent
- Missing Test #2: Rate limit metadata format tests

---

## Test Coverage Summary

### Existing Test Files
1. `/Users/brian/Developer/cline/vend/tests/Unit/Data/ErrorDataTest.php` - Comprehensive error data tests
2. `/Users/brian/Developer/cline/vend/tests/Unit/Data/ResponseDataTest.php` - Response serialization tests
3. `/Users/brian/Developer/cline/vend/tests/Unit/Exceptions/TooManyRequestsExceptionTest.php` - Rate limit exception tests
4. `/Users/brian/Developer/cline/vend/tests/Unit/Data/RequestObjectDataTest.php` - Context access tests (line 350)
5. 28+ exception test files covering all error types

### Test Coverage Strengths
- All error codes tested for client/server classification
- Error data creation and serialization tested
- Exception-to-error conversion tested
- HTTP status code mapping tested
- Context field access tested

### Test Coverage Gaps
See "Missing Tests" section above for details on:
1. Single vs multiple error format validation
2. Rate limit metadata structure
3. Context propagation behavior

---

## Recommendations Priority

### High Priority
1. **Clarify single vs multiple error format**: Update either implementation or documentation for consistency
2. **Implement rate limit metadata**: Create structured rate limit metadata for proactive client throttling

### Medium Priority
3. **Fix rate limit error details**: Update TooManyRequestsException to match documentation structure
4. **Add context propagation helper**: Simplify downstream call context creation

### Low Priority
5. **Add validation for empty errors array**: Enforce "at least one error" requirement
6. **Add missing test coverage**: Fill gaps in format validation and integration testing

---

## Overall Assessment

The Forrst implementation demonstrates **strong compliance** with the errors, context, and rate limiting documentation. Core functionality is well-implemented with proper error codes, retryable flags, source pointers, and context propagation support.

The main gaps are:
1. **Format inconsistencies** between single/multiple error formats
2. **Missing rate limit metadata** for proactive throttling
3. **Test coverage gaps** in format validation and integration scenarios

These issues are relatively minor and don't affect core protocol compliance, but addressing them would improve client experience and ensure full specification adherence.
