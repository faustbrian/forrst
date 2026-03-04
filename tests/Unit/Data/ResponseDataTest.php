<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\InternalErrorException;

describe('ResponseData', function (): void {
    describe('Happy Paths', function (): void {
        test('creates success response via factory method', function (): void {
            // Arrange
            $result = ['status' => 'ok'];

            // Act
            $response = ResponseData::success($result, 'request-123');

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($response->protocol->name)->toBe('forrst')
                ->and($response->protocol->version)->toBe('0.1.0')
                ->and($response->id)->toBe('request-123')
                ->and($response->result)->toBe(['status' => 'ok'])
                ->and($response->getFirstError())->toBeNull()
                ->and($response->isSuccessful())->toBeTrue();
        });

        test('creates success response with metadata', function (): void {
            // Arrange
            $result = ['data' => 'test'];
            $meta = ['duration_ms' => 42, 'server' => 'api-1'];

            // Act
            $response = ResponseData::success($result, 'req-1', meta: $meta);

            // Assert
            expect($response->protocol->name)->toBe('forrst')
                ->and($response->meta)->toBe(['duration_ms' => 42, 'server' => 'api-1']);
        });

        test('creates error response from request exception', function (): void {
            // Arrange
            $exception = InternalErrorException::create(
                new Exception('Test error'),
            );

            // Act
            $response = ResponseData::fromException($exception, 'request-456');

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($response->id)->toBe('request-456')
                ->and($response->getFirstError())->toBeInstanceOf(ErrorData::class)
                ->and($response->getFirstError()->code)->toBe(ErrorCode::InternalError->value)
                ->and($response->getFirstError()->message)->toBe('Internal error')
                ->and($response->isFailed())->toBeTrue();
        });

        test('creates error response via error factory method', function (): void {
            // Arrange
            $errorData = new ErrorData(
                code: ErrorCode::InternalError,
                message: 'Internal error',
            );

            // Act
            $response = ResponseData::error($errorData, 'request-789');

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->protocol)->toBeInstanceOf(ProtocolData::class)
                ->and($response->getFirstError()->code)->toBe(ErrorCode::InternalError->value);
        });

        test('identifies successful response with result data', function (): void {
            // Arrange
            $response = ResponseData::success(['data' => 'success'], 'req-1');

            // Act
            $isSuccessful = $response->isSuccessful();

            // Assert
            expect($isSuccessful)->toBeTrue()
                ->and($response->isFailed())->toBeFalse();
        });

        test('detects server error in isFailed method', function (): void {
            // Arrange
            $errorData = new ErrorData(
                code: ErrorCode::InternalError,
                message: 'Internal error',
            );
            $response = ResponseData::error($errorData, 'req-1');

            // Act
            $isFailed = $response->isFailed();
            $isServerError = $response->isServerError();

            // Assert
            expect($isFailed)->toBeTrue()
                ->and($isServerError)->toBeTrue()
                ->and($response->isSuccessful())->toBeFalse();
        });

        test('converts successful response to array correctly', function (): void {
            // Arrange
            $response = ResponseData::success(['status' => 'ok'], 'req-123');

            // Act
            $array = $response->toArray();

            // Assert
            expect($array['protocol'])->toBe(['name' => 'forrst', 'version' => '0.1.0'])
                ->and($array['id'])->toBe('req-123')
                ->and($array['result'])->toBe(['status' => 'ok'])
                ->and($array)->not->toHaveKey('errors');
        });

        test('converts error response to array correctly', function (): void {
            // Arrange
            $errorData = new ErrorData(
                code: ErrorCode::InvalidRequest,
                message: 'Invalid Request',
            );
            $response = ResponseData::error($errorData, 'req-1');

            // Act
            $array = $response->toArray();

            // Assert - errors always use 'errors' array
            expect($array)->toHaveKey('protocol')
                ->and($array)->toHaveKey('id')
                ->and($array)->toHaveKey('errors')
                ->and($array['result'])->toBeNull();
        });

        test('includes metadata in array when present', function (): void {
            // Arrange
            $response = ResponseData::success(
                ['data' => 'test'],
                'req-1',
                meta: ['duration_ms' => 100],
            );

            // Act
            $array = $response->toArray();

            // Assert
            expect($array)->toHaveKey('meta')
                ->and($array['meta'])->toBe(['duration_ms' => 100]);
        });
    });

    describe('Sad Paths', function (): void {
        test('detects client error as failed response', function (): void {
            // Arrange
            $errorData = new ErrorData(
                code: ErrorCode::InvalidRequest,
                message: 'Invalid Request',
            );
            $response = ResponseData::error($errorData, 'req-1');

            // Act
            $isFailed = $response->isFailed();
            $isClientError = $response->isClientError();

            // Assert
            expect($isFailed)->toBeTrue()
                ->and($isClientError)->toBeTrue()
                ->and($response->isServerError())->toBeFalse()
                ->and($response->isSuccessful())->toBeFalse();
        });

        test('response without error is not failed', function (): void {
            // Arrange
            $response = ResponseData::success('success', 'req-1');

            // Act
            $isClientError = $response->isClientError();
            $isServerError = $response->isServerError();

            // Assert
            expect($isClientError)->toBeFalse()
                ->and($isServerError)->toBeFalse()
                ->and($response->isFailed())->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles all server error codes', function (ErrorCode $code): void {
            // Arrange
            $errorData = new ErrorData(
                code: $code,
                message: 'Test error',
            );
            $response = ResponseData::error($errorData, 'req-1');

            // Act
            $isServerError = $response->isServerError();
            $isFailed = $response->isFailed();

            // Assert
            expect($isServerError)->toBeTrue()
                ->and($isFailed)->toBeTrue()
                ->and($response->isClientError())->toBeFalse();
        })->with([
            'internal error' => ErrorCode::InternalError,
            'unavailable' => ErrorCode::Unavailable,
            'dependency error' => ErrorCode::DependencyError,
        ]);

        test('handles all client error codes', function (ErrorCode $code): void {
            // Arrange
            $errorData = new ErrorData(
                code: $code,
                message: 'Test error',
            );
            $response = ResponseData::error($errorData, 'req-1');

            // Act
            $isClientError = $response->isClientError();
            $isFailed = $response->isFailed();
            $isServerError = $response->isServerError();

            // Assert
            expect($isClientError)->toBeTrue()
                ->and($isFailed)->toBeTrue()
                ->and($isServerError)->toBeFalse();
        })->with([
            'parse error' => ErrorCode::ParseError,
            'invalid request' => ErrorCode::InvalidRequest,
            'function not found' => ErrorCode::FunctionNotFound,
            'invalid arguments' => ErrorCode::InvalidArguments,
            'schema validation failed' => ErrorCode::SchemaValidationFailed,
            'unauthorized' => ErrorCode::Unauthorized,
            'forbidden' => ErrorCode::Forbidden,
        ]);

        test('handles response with null result', function (): void {
            // Arrange
            $response = ResponseData::success(null, 'req-1');

            // Act & Assert
            expect($response->result)->toBeNull()
                ->and($response->getFirstError())->toBeNull()
                ->and($response->isSuccessful())->toBeTrue();
        });

        test('handles complex result data', function (): void {
            // Arrange
            $complexResult = [
                'user' => [
                    'id' => 123,
                    'profile' => [
                        'name' => 'John',
                        'emails' => ['a@b.com', 'c@d.com'],
                    ],
                ],
                'metadata' => ['timestamp' => '2025-10-11T00:00:00Z'],
            ];

            $response = ResponseData::success($complexResult, 'req-1');

            // Act & Assert
            expect($response->result['user']['profile']['name'])->toBe('John')
                ->and($response->result['user']['profile']['emails'])->toHaveCount(2);
        });

        test('handles unicode characters in result', function (): void {
            // Arrange
            $response = ResponseData::success(
                ['message' => '你好世界 🌍'],
                'req-1',
            );

            // Assert
            expect($response->result['message'])->toBe('你好世界 🌍');
        });

        test('handles custom Server prefixed error codes as unclassified', function (): void {
            // Arrange
            $errorData = new ErrorData(
                code: 'ServerCustomError',
                message: 'Custom server error',
            );
            $response = ResponseData::error($errorData, 'req-1');

            // Assert - Custom string codes are not classified by prefix
            expect($response->isServerError())->toBeFalse()
                ->and($response->isClientError())->toBeFalse()
                ->and($response->isFailed())->toBeTrue();
        });

        test('handles unknown error codes as unclassified', function (): void {
            // Arrange
            $errorData = new ErrorData(
                code: 'UnknownError',
                message: 'Unknown error',
            );
            $response = ResponseData::error($errorData, 'req-1');

            // Assert - Unknown codes are not classified as client or server errors
            expect($response->isFailed())->toBeTrue()
                ->and($response->isServerError())->toBeFalse()
                ->and($response->isClientError())->toBeFalse();
        });
    });

    describe('Regression Tests', function (): void {
        test('ensures toArray includes result field on success', function (): void {
            // Arrange
            $response = ResponseData::success(['status' => 'success'], 'req-1');

            // Act
            $array = $response->toArray();

            // Assert - Success response only has result, not errors
            expect($array)->toHaveKey('result')
                ->and($array)->not->toHaveKey('errors');
        });

        test('ensures error response has null result in array', function (): void {
            // Arrange
            $errorData = new ErrorData(
                code: ErrorCode::InternalError,
                message: 'Internal error',
            );
            $response = ResponseData::error($errorData, 'req-1');

            // Act
            $array = $response->toArray();

            // Assert - errors always use 'errors' array
            expect($array['result'])->toBeNull()
                ->and($array)->toHaveKey('errors');
        });

        test('validates success factory produces exact structure', function (): void {
            // Arrange & Act
            $response = ResponseData::success(['data' => 'test'], 'req-1');
            $array = $response->toArray();

            // Assert
            expect($array['protocol'])->toBe(['name' => 'forrst', 'version' => '0.1.0'])
                ->and($array['id'])->toBe('req-1')
                ->and($array['result'])->toBe(['data' => 'test'])
                ->and($array)->not->toHaveKey('errors');
        });

        test('ensures all predefined error codes are correctly classified', function (): void {
            // Client errors
            $clientCodes = [
                ErrorCode::ParseError,
                ErrorCode::InvalidRequest,
                ErrorCode::FunctionNotFound,
                ErrorCode::InvalidArguments,
                ErrorCode::SchemaValidationFailed,
                ErrorCode::Unauthorized,
                ErrorCode::Forbidden,
            ];

            foreach ($clientCodes as $code) {
                $error = new ErrorData(code: $code, message: 'Test');
                $response = ResponseData::error($error, 'req-1');
                expect($response->isClientError())->toBeTrue();
                expect($response->isServerError())->toBeFalse();
            }

            // Server errors
            $serverCodes = [
                ErrorCode::InternalError,
                ErrorCode::Unavailable,
                ErrorCode::DependencyError,
            ];

            foreach ($serverCodes as $code) {
                $error = new ErrorData(code: $code, message: 'Test');
                $response = ResponseData::error($error, 'req-1');
                expect($response->isServerError())->toBeTrue();
                expect($response->isClientError())->toBeFalse();
            }
        });
    });
});
