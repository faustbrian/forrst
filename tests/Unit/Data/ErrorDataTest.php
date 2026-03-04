<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Enums\ErrorCode;

describe('ErrorData', function (): void {
    describe('Happy Paths', function (): void {
        test('creates error data with code, message and data', function (): void {
            $error = new ErrorData(
                code: ErrorCode::InvalidRequest,
                message: 'Invalid Request',
                details: ['additional' => 'info'],
            );

            expect($error)->toBeInstanceOf(ErrorData::class)
                ->and($error->code)->toBe(ErrorCode::InvalidRequest->value)
                ->and($error->message)->toBe('Invalid Request')
                ->and($error->details)->toBe(['additional' => 'info']);
        });

        test('creates error data without additional data', function (): void {
            $error = new ErrorData(
                code: ErrorCode::FunctionNotFound,
                message: 'Function not found',
            );

            expect($error)->toBeInstanceOf(ErrorData::class)
                ->and($error->code)->toBe(ErrorCode::FunctionNotFound->value)
                ->and($error->message)->toBe('Function not found')
                ->and($error->details)->toBeNull();
        });

        test('creates from array using inherited from method', function (): void {
            $error = ErrorData::from([
                'code' => ErrorCode::InvalidArguments->value,
                'message' => 'Invalid arguments',
                'details' => ['info' => 'Extra information'],
            ]);

            expect($error)->toBeInstanceOf(ErrorData::class)
                ->and($error->code)->toBe(ErrorCode::InvalidArguments->value)
                ->and($error->message)->toBe('Invalid arguments')
                ->and($error->details)->toBe(['info' => 'Extra information']);
        });
    });

    describe('Client Error Detection', function (): void {
        test('identifies invalid request as client error', function (): void {
            $error = new ErrorData(ErrorCode::InvalidRequest, 'Invalid Request');

            expect($error->isClient())->toBeTrue()
                ->and($error->isServer())->toBeFalse();
        });

        test('identifies function not found as client error', function (): void {
            $error = new ErrorData(ErrorCode::FunctionNotFound, 'Function not found');

            expect($error->isClient())->toBeTrue()
                ->and($error->isServer())->toBeFalse();
        });

        test('identifies invalid arguments as client error', function (): void {
            $error = new ErrorData(ErrorCode::InvalidArguments, 'Invalid arguments');

            expect($error->isClient())->toBeTrue()
                ->and($error->isServer())->toBeFalse();
        });

        test('identifies parse error as client error', function (): void {
            $error = new ErrorData(ErrorCode::ParseError, 'Parse error');

            expect($error->isClient())->toBeTrue()
                ->and($error->isServer())->toBeFalse();
        });

        test('identifies validation error as client error', function (): void {
            $error = new ErrorData(ErrorCode::SchemaValidationFailed, 'Validation error');

            expect($error->isClient())->toBeTrue()
                ->and($error->isServer())->toBeFalse();
        });

        test('identifies unauthorized as client error', function (): void {
            $error = new ErrorData(ErrorCode::Unauthorized, 'Unauthorized');

            expect($error->isClient())->toBeTrue()
                ->and($error->isServer())->toBeFalse();
        });

        test('identifies forbidden as client error', function (): void {
            $error = new ErrorData(ErrorCode::Forbidden, 'Forbidden');

            expect($error->isClient())->toBeTrue()
                ->and($error->isServer())->toBeFalse();
        });

        test('identifies rate limited as operational error (neither client nor server)', function (): void {
            $error = new ErrorData(ErrorCode::RateLimited, 'Rate limited');

            // RateLimited is retryable but classified as neither client nor server
            expect($error->isClient())->toBeFalse()
                ->and($error->isServer())->toBeFalse();
        });

        test('does not identify internal error as client error', function (): void {
            $error = new ErrorData(ErrorCode::InternalError, 'Internal error');

            expect($error->isClient())->toBeFalse();
        });
    });

    describe('Server Error Detection', function (): void {
        test('identifies internal error as server error', function (): void {
            $error = new ErrorData(ErrorCode::InternalError, 'Internal error');

            expect($error->isServer())->toBeTrue()
                ->and($error->isClient())->toBeFalse();
        });

        test('identifies server error as server error', function (): void {
            $error = new ErrorData(ErrorCode::InternalError, 'Server error');

            expect($error->isServer())->toBeTrue()
                ->and($error->isClient())->toBeFalse();
        });

        test('identifies service unavailable as server error', function (): void {
            $error = new ErrorData(ErrorCode::Unavailable, 'Service unavailable');

            expect($error->isServer())->toBeTrue()
                ->and($error->isClient())->toBeFalse();
        });

        test('custom Server prefixed errors are unclassified', function (): void {
            $error = new ErrorData('ServerCustomError', 'Custom server error');

            // Custom string codes are not classified by prefix
            expect($error->isServer())->toBeFalse()
                ->and($error->isClient())->toBeFalse();
        });

        test('does not identify client errors as server errors', function (): void {
            $error1 = new ErrorData(ErrorCode::InvalidRequest, 'Invalid Request');
            expect($error1->isServer())->toBeFalse();

            $error2 = new ErrorData(ErrorCode::FunctionNotFound, 'Function not found');
            expect($error2->isServer())->toBeFalse();

            $error3 = new ErrorData(ErrorCode::InvalidArguments, 'Invalid arguments');
            expect($error3->isServer())->toBeFalse();
        });

        test('does not identify unknown errors as server errors', function (): void {
            $error = new ErrorData('UnknownError', 'Unknown error');

            expect($error->isServer())->toBeFalse()
                ->and($error->isClient())->toBeFalse();
        });
    });

    describe('HTTP Status Code Mapping', function (): void {
        test('maps parse error to 400 status code', function (): void {
            $error = new ErrorData(ErrorCode::ParseError, 'Parse error');

            expect($error->toStatusCode())->toBe(400);
        });

        test('maps invalid request to 400 status code', function (): void {
            $error = new ErrorData(ErrorCode::InvalidRequest, 'Invalid Request');

            expect($error->toStatusCode())->toBe(400);
        });

        test('maps function not found to 404 status code', function (): void {
            $error = new ErrorData(ErrorCode::FunctionNotFound, 'Function not found');

            expect($error->toStatusCode())->toBe(404);
        });

        test('maps invalid arguments to 400 status code', function (): void {
            $error = new ErrorData(ErrorCode::InvalidArguments, 'Invalid arguments');

            expect($error->toStatusCode())->toBe(400);
        });

        test('maps validation error to 422 status code', function (): void {
            $error = new ErrorData(ErrorCode::SchemaValidationFailed, 'Validation error');

            expect($error->toStatusCode())->toBe(422);
        });

        test('maps unauthorized to 401 status code', function (): void {
            $error = new ErrorData(ErrorCode::Unauthorized, 'Unauthorized');

            expect($error->toStatusCode())->toBe(401);
        });

        test('maps forbidden to 403 status code', function (): void {
            $error = new ErrorData(ErrorCode::Forbidden, 'Forbidden');

            expect($error->toStatusCode())->toBe(403);
        });

        test('maps rate limited to 429 status code', function (): void {
            $error = new ErrorData(ErrorCode::RateLimited, 'Rate limited');

            expect($error->toStatusCode())->toBe(429);
        });

        test('maps internal error to 500 status code', function (): void {
            $error = new ErrorData(ErrorCode::InternalError, 'Internal error');

            expect($error->toStatusCode())->toBe(500);
        });

        test('maps server error to 500 status code', function (): void {
            $error = new ErrorData(ErrorCode::InternalError, 'Server error');

            expect($error->toStatusCode())->toBe(500);
        });

        test('maps service unavailable to 503 status code', function (): void {
            $error = new ErrorData(ErrorCode::Unavailable, 'Service unavailable');

            expect($error->toStatusCode())->toBe(503);
        });

        test('maps custom prefixed errors to 400 status code', function (): void {
            // Custom string codes are not classified by prefix, fall back to 400
            $error = new ErrorData('ServerCustom', 'Custom server error');

            expect($error->toStatusCode())->toBe(400);
        });

        test('maps unknown errors to 400 status code', function (): void {
            $error = new ErrorData('UnknownError', 'Unknown error');

            expect($error->toStatusCode())->toBe(400);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty string error code', function (): void {
            $error = new ErrorData('', 'Empty code');

            expect($error->isClient())->toBeFalse()
                ->and($error->isServer())->toBeFalse()
                ->and($error->toStatusCode())->toBe(400);
        });

        test('handles error with null data field', function (): void {
            $error = new ErrorData(ErrorCode::InvalidRequest, 'Error with null data');

            expect($error->details)->toBeNull();

            // When converting to array, null values should be removed
            $array = $error->toArray();
            expect($array)->not->toHaveKey('details')
                ->and($array['code'])->toBe(ErrorCode::InvalidRequest->value)
                ->and($array['message'])->toBe('Error with null data');
        });

        test('handles error with empty string data', function (): void {
            $error = new ErrorData(ErrorCode::InternalError, 'Error with empty string', details: ['value' => '']);

            expect($error->details)->toBe(['value' => '']);

            $array = $error->toArray();
            expect($array)->toHaveKey('details')
                ->and($array['details'])->toBe(['value' => '']);
        });

        test('handles error with different data types in details array', function (): void {
            // String value in details
            $stringError = new ErrorData(ErrorCode::InvalidRequest, 'String data error', details: ['message' => 'error details']);
            expect($stringError->details)->toBe(['message' => 'error details']);

            // Integer value in details
            $intError = new ErrorData(ErrorCode::FunctionNotFound, 'Integer data error', details: ['count' => 42]);
            expect($intError->details)->toBe(['count' => 42]);

            // Boolean value in details
            $boolError = new ErrorData(ErrorCode::InvalidArguments, 'Boolean data error', details: ['valid' => false]);
            expect($boolError->details)->toBe(['valid' => false]);

            // Float value in details
            $floatError = new ErrorData(ErrorCode::InternalError, 'Float data error', details: ['value' => 3.14]);
            expect($floatError->details)->toBe(['value' => 3.14]);
        });

        test('handles error with complex data structures', function (): void {
            $complexData = [
                'nested' => [
                    'array' => ['with', 'values'],
                    'object' => ['key' => 'value'],
                ],
                'string' => 'data',
                'number' => 123,
                'boolean' => true,
            ];

            $error = new ErrorData(ErrorCode::InternalError, 'Complex error', details: $complexData);

            expect($error->details)->toBe($complexData);
        });

        test('handles error with unicode characters in message', function (): void {
            $unicodeMessage = 'Error: 日本語 Ошибка';
            $error = new ErrorData(ErrorCode::InvalidRequest, $unicodeMessage);

            expect($error->message)->toBe($unicodeMessage);
        });

        test('handles error with very long message', function (): void {
            $longMessage = str_repeat('Error details ', 100);
            $error = new ErrorData(ErrorCode::InternalError, $longMessage);

            expect($error->message)->toBe($longMessage)
                ->and(mb_strlen($error->message))->toBeGreaterThan(1_000);
        });
    });

    describe('Regression Tests', function (): void {
        test('ensures all predefined error codes map to correct status codes', function (): void {
            // Comprehensive test of all predefined codes
            $mappings = [
                [ErrorCode::ParseError, 400],
                [ErrorCode::InvalidRequest, 400],
                [ErrorCode::FunctionNotFound, 404],
                [ErrorCode::InvalidArguments, 400],
                [ErrorCode::SchemaValidationFailed, 422],
                [ErrorCode::Unauthorized, 401],
                [ErrorCode::Forbidden, 403],
                [ErrorCode::RateLimited, 429],
                [ErrorCode::InternalError, 500],
                [ErrorCode::InternalError, 500],
                [ErrorCode::Unavailable, 503],
            ];

            foreach ($mappings as [$code, $expectedStatus]) {
                $error = new ErrorData($code, 'Test message');
                expect($error->toStatusCode())->toBe($expectedStatus);
            }
        });

        test('ensures all client error codes are correctly classified', function (): void {
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
                $error = new ErrorData($code, 'Test');
                expect($error->isClient())->toBeTrue();
                expect($error->isServer())->toBeFalse();
            }
        });

        test('ensures all server error codes are correctly classified', function (): void {
            $serverCodes = [
                ErrorCode::InternalError,
                ErrorCode::Unavailable,
                ErrorCode::DependencyError,
            ];

            foreach ($serverCodes as $code) {
                $error = new ErrorData($code, 'Test');
                expect($error->isServer())->toBeTrue();
                expect($error->isClient())->toBeFalse();
            }
        });
    });
});
