<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\AbstractRequestException;
use Illuminate\Support\Facades\Config;
use Tests\Unit\Exceptions\Fixtures\ConcreteRequestException;

describe('AbstractRequestException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with error data', function (): void {
            // Arrange
            $errorData = ErrorData::from([
                'code' => ErrorCode::InvalidRequest->value,
                'message' => 'Invalid request',
                'details' => ['detail' => 'Missing required field'],
            ]);

            // Act
            $exception = new ConcreteRequestException($errorData);

            // Assert
            expect($exception)->toBeInstanceOf(AbstractRequestException::class);
            expect($exception->getErrorCode())->toBe(ErrorCode::InvalidRequest->value);
            expect($exception->getErrorMessage())->toBe('Invalid request');
            expect($exception->getErrorDetails())->toBe(['detail' => 'Missing required field']);
        });

        test('returns error data via getErrorDetails', function (): void {
            // Arrange
            $data = ['validation' => ['field' => 'email', 'error' => 'invalid format']];
            $exception = ConcreteRequestException::make(ErrorCode::InvalidArguments, 'Invalid arguments', $data);

            // Act
            $result = $exception->getErrorDetails();

            // Assert
            expect($result)->toBe($data);
        });

        test('converts exception to error data object', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::FunctionNotFound, 'Function not found');

            // Act
            $errorData = $exception->toError();

            // Assert
            expect($errorData)->toBeInstanceOf(ErrorData::class);
            expect($errorData->code)->toBe(ErrorCode::FunctionNotFound->value);
            expect($errorData->message)->toBe('Function not found');
        });

        test('converts exception to array representation', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(
                ErrorCode::InvalidArguments,
                'Invalid arguments',
                ['field' => 'email'],
            );

            // Act
            $array = $exception->toArray();

            // Assert
            expect($array)->toHaveKeys(['code', 'message', 'details']);
            expect($array['code'])->toBe(ErrorCode::InvalidArguments->value);
            expect($array['message'])->toBe('Invalid arguments');
            expect($array['details'])->toBe(['field' => 'email']);
        });

        test('returns empty headers by default', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::InvalidRequest, 'Invalid request');

            // Act
            $headers = $exception->getHeaders();

            // Assert
            expect($headers)->toBe([]);
            expect($headers)->toBeArray();
            expect($headers)->toBeEmpty();
        });
    });

    describe('Sad Paths', function (): void {
        test('handles exception with null data', function (): void {
            // Arrange & Act
            $exception = ConcreteRequestException::make(ErrorCode::InternalError, 'Internal error');

            // Assert
            expect($exception->getErrorDetails())->toBeNull();
            expect($exception->toArray())->toHaveKey('code');
            expect($exception->toArray())->toHaveKey('message');
        });
    });

    describe('Edge Cases', function (): void {
        test('includes debug information when debug mode is enabled', function (): void {
            // Arrange
            Config::set('app.debug', true);
            $exception = ConcreteRequestException::make(ErrorCode::InternalError, 'Internal error');

            // Act
            $array = $exception->toArray();

            // Assert
            expect($array)->toHaveKey('details');
            expect($array['details'])->toHaveKey('debug');
            expect($array['details']['debug'])->toHaveKeys(['file', 'line', 'trace']);
            expect($array['details']['debug']['file'])->toBeString();
            expect($array['details']['debug']['line'])->toBeInt();
            expect($array['details']['debug']['trace'])->toBeString();
        });

        test('excludes debug information when debug mode is disabled', function (): void {
            // Arrange
            Config::set('app.debug', false);
            $exception = ConcreteRequestException::make(ErrorCode::InternalError, 'Internal error');

            // Act
            $array = $exception->toArray();

            // Assert
            expect($array)->toHaveKeys(['code', 'message']);
            expect($array)->not->toHaveKey('details');
        });

        test('filters out null values in array representation', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::InvalidRequest, 'Invalid request');
            Config::set('app.debug', false);

            // Act
            $array = $exception->toArray();

            // Assert
            expect($array)->not->toHaveKey('details');
            expect($array)->toHaveCount(2); // Only code and message
        });

        test('merges debug data with existing error data', function (): void {
            // Arrange
            Config::set('app.debug', true);
            $existingData = ['validation' => 'failed'];
            $exception = ConcreteRequestException::make(ErrorCode::InvalidArguments, 'Invalid arguments', $existingData);

            // Act
            $array = $exception->toArray();

            // Assert
            expect($array['details'])->toHaveKey('validation');
            expect($array['details'])->toHaveKey('debug');
            expect($array['details']['validation'])->toBe('failed');
            expect($array['details']['debug'])->toHaveKeys(['file', 'line', 'trace']);
        });

        test('includes empty details array', function (): void {
            // Arrange
            Config::set('app.debug', false);
            $exception = ConcreteRequestException::make(ErrorCode::InvalidRequest, 'Invalid request', []);

            // Act
            $array = $exception->toArray();

            // Assert
            expect($array)->toHaveKeys(['code', 'message', 'details'])
                ->and($array['details'])->toBe([]);
        });
    });

    describe('getStatusCode - Standard Error Codes', function (): void {
        test('returns 400 for parse error', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::ParseError, 'Parse error');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(400);
        });

        test('returns 400 for invalid request', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::InvalidRequest, 'Invalid request');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(400);
        });

        test('returns 404 for function not found', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::FunctionNotFound, 'Function not found');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(404);
        });

        test('returns 400 for invalid arguments', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::InvalidArguments, 'Invalid arguments');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(400);
        });

        test('returns 500 for internal error', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::InternalError, 'Internal error');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(500);
        });

        test('returns 500 for server error', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::InternalError, 'Server error');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(500);
        });

        test('returns 401 for unauthorized', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::Unauthorized, 'Unauthorized');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(401);
        });

        test('returns 403 for forbidden', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::Forbidden, 'Forbidden');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(403);
        });

        test('returns 422 for validation error', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::SchemaValidationFailed, 'Validation error');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(422);
        });

        test('returns 429 for rate limited', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::RateLimited, 'Rate limited');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(429);
        });

        test('returns 503 for service unavailable', function (): void {
            // Arrange
            $exception = ConcreteRequestException::make(ErrorCode::Unavailable, 'Service unavailable');

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(503);
        });
    });
});
