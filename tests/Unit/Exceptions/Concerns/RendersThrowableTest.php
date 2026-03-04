<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\InternalErrorException;
use Cline\Forrst\Exceptions\UnauthorizedException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\Support\Fakes\ExceptionHandlerWithTrait;

describe('RendersThrowable', function (): void {
    beforeEach(function (): void {
        // Create a test class that uses the trait
        $this->handler = new ExceptionHandlerWithTrait();
    });

    test('renderableThrowable registers a renderable closure', function (): void {
        // Arrange
        $closures = [];
        $this->handler->setRenderableCallback(function ($closure) use (&$closures): void {
            $closures[] = $closure;
        });

        // Act
        $this->handler->callRenderableThrowable();

        // Assert
        expect($closures)->toHaveCount(1);
        expect($closures[0])->toBeCallable();
    });

    test('returns null when request does not want JSON', function (): void {
        // Arrange
        $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->headers->set('Accept', 'text/html');

        $exception = new Exception('Test error');

        $this->handler->setRenderableCallback(function ($closure) use ($exception, $request): void {
            // Act
            $result = $closure($exception, $request);

            // Assert
            expect($result)->toBeNull();
        });

        // Act
        $this->handler->callRenderableThrowable();
    });

    test('maps exception through ExceptionMapper for JSON requests', function (): void {
        // Arrange
        $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->headers->set('Accept', 'application/json');

        $originalException = new Exception('Test error');

        $this->handler->setRenderableCallback(function ($closure) use ($originalException, $request): void {
            // Act
            $response = $closure($originalException, $request);

            // Assert
            expect($response)->not->toBeNull();
            expect($response->getStatusCode())->toBe(500);

            $data = json_decode((string) $response->getContent(), true);
            expect($data)->toHaveKey('protocol');
            expect($data['protocol'])->toBe(['name' => 'forrst', 'version' => '0.1.0']);
            expect($data)->toHaveKey('errors');
            expect($data['errors'][0]['code'])->toBe(ErrorCode::InternalError->value);
            expect($data['errors'][0]['message'])->toBe('Internal error');
        });

        // Act
        $this->handler->callRenderableThrowable();
    });

    test('includes request ID in response when present', function (): void {
        // Arrange
        $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $request->headers->set('Accept', 'application/json');
        $request->merge(['id' => 'test-id-123']);

        $exception = new Exception('Test error');

        $this->handler->setRenderableCallback(function ($closure) use ($exception, $request): void {
            // Act
            $response = $closure($exception, $request);

            // Assert
            $data = json_decode((string) $response->getContent(), true);
            expect($data)->toHaveKey('id', 'test-id-123');
        });

        // Act
        $this->handler->callRenderableThrowable();
    });

    test('generates ID when not present in request', function (): void {
        // Arrange
        $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $request->headers->set('Accept', 'application/json');

        $exception = new Exception('Test error');

        $this->handler->setRenderableCallback(function ($closure) use ($exception, $request): void {
            // Act
            $response = $closure($exception, $request);

            // Assert
            $data = json_decode((string) $response->getContent(), true);
            expect($data)->toHaveKey('id');
            expect($data['id'])->toBeString();
            expect($data['id'])->not->toBeEmpty();
        });

        // Act
        $this->handler->callRenderableThrowable();
    });

    test('uses status code from mapped exception', function (): void {
        // Arrange
        $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->headers->set('Accept', 'application/json');

        $authException = new AuthenticationException('Unauthenticated');

        $this->handler->setRenderableCallback(function ($closure) use ($authException, $request): void {
            // Act
            $response = $closure($authException, $request);

            // Assert
            expect($response->getStatusCode())->toBe(401);
        });

        // Act
        $this->handler->callRenderableThrowable();
    });

    test('includes headers from mapped exception', function (): void {
        // Arrange
        $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->headers->set('Accept', 'application/json');

        // Create an exception that has headers (UnauthorizedException)
        $unauthorizedException = UnauthorizedException::create();

        $this->handler->setRenderableCallback(function ($closure) use ($unauthorizedException, $request): void {
            // Act
            $response = $closure($unauthorizedException, $request);

            // Assert
            $headers = $response->headers->all();
            expect($headers)->toBeArray();
        });

        // Act
        $this->handler->callRenderableThrowable();
    });

    test('maps ValidationException to SemanticValidationException', function (): void {
        // Arrange
        $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $request->headers->set('Accept', 'application/json');

        $validator = Validator::make(['email' => 'invalid'], ['email' => ['required', 'email']]);
        $validationException = new ValidationException($validator);

        $this->handler->setRenderableCallback(function ($closure) use ($validationException, $request): void {
            // Act
            $response = $closure($validationException, $request);

            // Assert
            expect($response->getStatusCode())->toBe(422);

            $data = json_decode((string) $response->getContent(), true);
            expect($data['errors'][0]['code'])->toBe(ErrorCode::SchemaValidationFailed->value);
            expect($data['errors'][0])->toHaveKey('message');
            expect($data['errors'][0])->toHaveKey('details');
        });

        // Act
        $this->handler->callRenderableThrowable();
    });

    test('preserves Forrst compliant exception without remapping', function (): void {
        // Arrange
        $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->headers->set('Accept', 'application/json');

        $forrstException = InternalErrorException::create(
            new Exception('Original error'),
        );

        $this->handler->setRenderableCallback(function ($closure) use ($forrstException, $request): void {
            // Act
            $response = $closure($forrstException, $request);

            // Assert
            expect($response->getStatusCode())->toBe(500);

            $data = json_decode((string) $response->getContent(), true);
            expect($data['errors'][0]['code'])->toBe(ErrorCode::InternalError->value);
            expect($data['errors'][0]['message'])->toBe('Internal error');
            expect($data['errors'][0]['details'])->toBeArray();
        });

        // Act
        $this->handler->callRenderableThrowable();
    });

    test('returns Forrst protocol compliant response structure', function (): void {
        // Arrange
        $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $request->headers->set('Accept', 'application/json');
        $request->merge(['id' => 'req-42']);

        $exception = new Exception('Test error');

        $this->handler->setRenderableCallback(function ($closure) use ($exception, $request): void {
            // Act
            $response = $closure($exception, $request);

            // Assert
            $data = json_decode((string) $response->getContent(), true);

            // Verify Forrst protocol structure
            expect($data)->toHaveKey('protocol');
            expect($data['protocol'])->toBe(['name' => 'forrst', 'version' => '0.1.0']);
            expect($data)->toHaveKey('id');
            expect($data['id'])->toBe('req-42');
            expect($data)->toHaveKey('result');
            expect($data['result'])->toBeNull();
            expect($data)->toHaveKey('errors');

            // Verify error object structure
            expect($data['errors'][0])->toHaveKeys(['code', 'message']);
            expect($data['errors'][0]['code'])->toBeString();
            expect($data['errors'][0]['message'])->toBeString();
        });

        // Act
        $this->handler->callRenderableThrowable();
    });
});
