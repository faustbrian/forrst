<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\Actions\RendersThrowable;
use Cline\Forrst\Exceptions\ForbiddenException;
use Cline\Forrst\Exceptions\InternalErrorException;
use Cline\Forrst\Exceptions\ResourceNotFoundException;
use Cline\Forrst\Exceptions\SemanticValidationException;
use Cline\Forrst\Exceptions\TooManyRequestsException;
use Cline\Forrst\Exceptions\UnauthorizedException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use Tests\Support\Fakes\CustomHeaderException;
use Tests\Support\Fakes\CustomStatusCodeException;

/**
 * @covers \Cline\Forrst\Exceptions\Actions\RendersThrowable
 */
describe('RendersThrowable', function (): void {
    afterEach(function (): void {
        Mockery::close();
    });

    describe('Happy Paths', function (): void {
        test('registers renderable on Exceptions instance', function (): void {
            // Arrange
            $exceptions = Mockery::mock(Exceptions::class);
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::type('callable'));

            // Act
            RendersThrowable::execute($exceptions);

            // Assert
            // Expectations are verified automatically by Mockery
        });

        test('returns JSON response for JSON request', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('test-id');

            $throwable = new Exception('Test exception');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($throwable, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            $data = json_decode((string) $result->getContent(), true);
            expect($data)->toHaveKeys(['protocol', 'id', 'result', 'errors']);
            expect($data['protocol'])->toBe(['name' => 'forrst', 'version' => '0.1.0']);
            expect($data['id'])->toBe('test-id');
        });

        test('maps exception through ExceptionMapper', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-123');

            $originalException = new RuntimeException('Original error');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($originalException, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            $data = json_decode((string) $result->getContent(), true);
            expect($data['errors'][0])->toHaveKeys(['code', 'message']);
            expect($data['errors'][0]['code'])->toBe(ErrorCode::InternalError->value);
        });

        test('includes request ID in response', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('unique-request-id');

            $throwable = new Exception('Test error');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($throwable, $request);

            // Assert
            $data = json_decode((string) $result->getContent(), true);
            expect($data['id'])->toBe('unique-request-id');
        });

        test('maps authentication exception to unauthorized error', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $exception = new AuthenticationException();

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($exception, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            expect($result->getStatusCode())->toBe(401);

            $data = json_decode((string) $result->getContent(), true);
            expect($data['errors'][0]['code'])->toBe(UnauthorizedException::create()->getErrorCode());
        });

        test('maps authorization exception to forbidden error', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $exception = new AuthorizationException();

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($exception, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            expect($result->getStatusCode())->toBe(403);

            $data = json_decode((string) $result->getContent(), true);
            expect($data['errors'][0]['code'])->toBe(ForbiddenException::create()->getErrorCode());
        });

        test('maps model not found exception to resource not found error', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $exception = new ModelNotFoundException();

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($exception, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            expect($result->getStatusCode())->toBe(404);

            $data = json_decode((string) $result->getContent(), true);
            expect($data['errors'][0]['code'])->toBe(ResourceNotFoundException::create()->getErrorCode());
        });

        test('maps item not found exception to resource not found error', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $exception = new ItemNotFoundException();

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($exception, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            expect($result->getStatusCode())->toBe(404);

            $data = json_decode((string) $result->getContent(), true);
            expect($data['errors'][0]['code'])->toBe(ResourceNotFoundException::create()->getErrorCode());
        });

        test('maps throttle requests exception to too many requests error', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $exception = new ThrottleRequestsException();

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($exception, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            expect($result->getStatusCode())->toBe(429);

            $data = json_decode((string) $result->getContent(), true);
            expect($data['errors'][0]['code'])->toBe(TooManyRequestsException::create()->getErrorCode());
        });

        test('maps validation exception to validation error with validation details', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $validator = Validator::make(['email' => 'invalid'], ['email' => ['required', 'email']]);
            $validator->fails();

            $exception = new ValidationException($validator);

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($exception, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            expect($result->getStatusCode())->toBe(422);

            $data = json_decode((string) $result->getContent(), true);
            expect($data['errors'][0]['code'])->toBe(SemanticValidationException::create()->getErrorCode());
            expect($data['errors'][0])->toHaveKey('details');
        });

        test('maps generic exception to internal error', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $exception = new RuntimeException('Something went wrong');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($exception, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            expect($result->getStatusCode())->toBe(500);

            $data = json_decode((string) $result->getContent(), true);
            expect($data['errors'][0]['code'])->toBe(InternalErrorException::create($exception)->getErrorCode());
        });
    });

    describe('Sad Paths', function (): void {
        test('returns null for non-JSON request', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(false);

            $throwable = new Exception('Test exception');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($throwable, $request);

            // Assert
            expect($result)->toBeNull();
        });

        test('generates ID when request ID is missing', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn(null);

            $throwable = new Exception('Test error');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($throwable, $request);

            // Assert
            $data = json_decode((string) $result->getContent(), true);
            expect($data)->toHaveKey('id');
            expect($data['id'])->toBeString();
            expect($data['id'])->not->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('response includes protocol and result fields', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('test-id');

            $throwable = new Exception('Test');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($throwable, $request);

            // Assert
            $data = json_decode((string) $result->getContent(), true);
            expect($data)->toHaveKeys(['protocol', 'id', 'result', 'errors']);
            expect($data['result'])->toBeNull();
            expect($data['protocol'])->toBe(['name' => 'forrst', 'version' => '0.1.0']);
        });

        test('preserves exception headers', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $customException = new CustomHeaderException(
                ErrorData::from(['code' => ErrorCode::InternalError->value, 'message' => 'Custom']),
            );

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($customException, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            expect($result->headers->get('X-Custom-Header'))->toBe('CustomValue');
        });

        test('preserves exception status code', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $customException = new CustomStatusCodeException(
                ErrorData::from(['code' => ErrorCode::FunctionNotFound->value, 'message' => 'Function not found']),
            );

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($customException, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            expect($result->getStatusCode())->toBe(404);
        });

        test('generates ID for request with empty string id', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('');

            $throwable = new Exception('Test');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($throwable, $request);

            // Assert
            $data = json_decode((string) $result->getContent(), true);
            expect($data['id'])->toBeString();
            expect($data['id'])->not->toBeEmpty();
        });

        test('handles request with string id', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('uuid-string-123');

            $throwable = new Exception('Test');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($throwable, $request);

            // Assert
            $data = json_decode((string) $result->getContent(), true);
            expect($data['id'])->toBe('uuid-string-123');
        });

        test('handles exception with empty message', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $throwable = new RuntimeException('');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($throwable, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            $data = json_decode((string) $result->getContent(), true);
            expect($data['errors'][0])->toHaveKey('message');
        });

        test('handles unicode characters in exception messages', function (): void {
            // Arrange
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('wantsJson')->once()->andReturn(true);
            $request->shouldReceive('input')->with('id')->once()->andReturn('req-1');

            $throwable = new RuntimeException('Error: 你好 🚀 café');

            $exceptions = Mockery::mock(Exceptions::class);
            $rendererCallback = null;
            $exceptions->shouldReceive('renderable')
                ->once()
                ->with(Mockery::on(function ($callback) use (&$rendererCallback): bool {
                    $rendererCallback = $callback;

                    return is_callable($callback);
                }));

            // Act
            RendersThrowable::execute($exceptions);
            $result = $rendererCallback($throwable, $request);

            // Assert
            expect($result)->toBeInstanceOf(JsonResponse::class);
            $content = $result->getContent();
            expect(json_decode($content, true))->not->toBeNull(); // Valid JSON
        });
    });
});
