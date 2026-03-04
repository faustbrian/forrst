<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Exceptions\AbstractRequestException;
use Cline\Forrst\Exceptions\TooManyRequestsException;

describe('TooManyRequestsException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates basic rate limit exception with default detail message', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::create();

            // Assert
            expect($exception)->toBeInstanceOf(AbstractRequestException::class);
            expect($exception->getErrorCode())->toBe(ErrorCode::RateLimited->value);
            expect($exception->getErrorMessage())->toBe('Rate limited');
            expect($exception->getStatusCode())->toBe(429);
        });

        test('creates basic rate limit exception with custom detail message', function (): void {
            // Arrange
            $customDetail = 'Rate limit: 100 requests per minute exceeded';

            // Act
            $exception = TooManyRequestsException::create($customDetail);

            // Assert
            expect($exception->getErrorDetails())->toBeArray();
            expect($exception->getErrorDetails())->toHaveCount(1);
            expect($exception->getErrorDetails()[0]['detail'])->toBe($customDetail);
        });

        test('creates exception with complete rate limit details for client backoff', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 100,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 60,
                retryAfterUnit: 'second',
            );

            // Assert
            expect($exception)->toBeInstanceOf(AbstractRequestException::class);
            expect($exception->getErrorCode())->toBe(ErrorCode::RateLimited->value);
            expect($exception->getErrorMessage())->toBe('Rate limited');
            expect($exception->getStatusCode())->toBe(429);
        });

        test('includes rate limit threshold in structured details', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 1_000,
                used: 950,
                windowValue: 1,
                windowUnit: 'hour',
                retryAfterValue: 10,
                retryAfterUnit: 'minute',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details)->toHaveKey('limit');
            expect($details['limit'])->toBe(1_000);
        });

        test('includes current usage count in structured details', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 100,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 60,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details)->toHaveKey('used');
            expect($details['used'])->toBe(100);
        });

        test('includes time window definition with value and unit', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 500,
                used: 500,
                windowValue: 15,
                windowUnit: 'minute',
                retryAfterValue: 900,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details)->toHaveKey('window');
            expect($details['window'])->toHaveKey('value');
            expect($details['window'])->toHaveKey('unit');
            expect($details['window']['value'])->toBe(15);
            expect($details['window']['unit'])->toBe('minute');
        });

        test('includes retry timing information with value and unit', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 100,
                windowValue: 1,
                windowUnit: 'hour',
                retryAfterValue: 3_600,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details)->toHaveKey('retry_after');
            expect($details['retry_after'])->toHaveKey('value');
            expect($details['retry_after'])->toHaveKey('unit');
            expect($details['retry_after']['value'])->toBe(3_600);
            expect($details['retry_after']['unit'])->toBe('second');
        });

        test('serializes to array with all rate limit information', function (): void {
            // Arrange
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 100,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 60,
                retryAfterUnit: 'second',
            );

            // Act
            $array = $exception->toArray();

            // Assert
            expect($array)->toHaveKeys(['code', 'message', 'details']);
            expect($array['code'])->toBe(ErrorCode::RateLimited->value);
            expect($array['message'])->toBe('Rate limited');
            expect($array['details'])->toHaveKeys(['limit', 'used', 'window', 'retry_after']);
        });

        test('returns 429 status code for HTTP responses', function (): void {
            // Arrange
            $exception = TooManyRequestsException::create();

            // Act
            $statusCode = $exception->getStatusCode();

            // Assert
            expect($statusCode)->toBe(429);
            expect($statusCode)->toBeInt();
        });
    });

    describe('Sad Paths', function (): void {
        test('handles zero limit indicating no requests allowed', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 0,
                used: 1,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 60,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['limit'])->toBe(0);
            expect($details['used'])->toBeGreaterThan($details['limit']);
        });

        test('handles excessive usage count far exceeding limit', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 1_000,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 60,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['used'])->toBe(1_000);
            expect($details['used'])->toBeGreaterThan($details['limit']);
        });

        test('uses default detail message when null is provided to create method', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::create();

            // Assert
            $details = $exception->getErrorDetails();
            expect($details[0]['detail'])->toBe('The server is refusing to service the request because the rate limit has been exceeded. Please wait and try again later.');
        });

        test('uses default detail message when null is provided to createWithDetails method', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 100,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 60,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['detail'])->toBe('The server is refusing to service the request because the rate limit has been exceeded. Please wait and try again later.');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles rate limit at exact threshold with equal used and limit', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 100,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 60,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['limit'])->toBe($details['used']);
            expect($details['limit'])->toBe(100);
        });

        test('handles very short time window of 1 second', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 10,
                used: 10,
                windowValue: 1,
                windowUnit: 'second',
                retryAfterValue: 1,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['window']['value'])->toBe(1);
            expect($details['window']['unit'])->toBe('second');
        });

        test('handles very long time window of 24 hours', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 10_000,
                used: 10_000,
                windowValue: 24,
                windowUnit: 'hour',
                retryAfterValue: 86_400,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['window']['value'])->toBe(24);
            expect($details['window']['unit'])->toBe('hour');
        });

        test('handles immediate retry with zero retry-after value', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 99,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 0,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['retry_after']['value'])->toBe(0);
        });

        test('handles large retry-after value for long backoff period', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 1,
                used: 100,
                windowValue: 1,
                windowUnit: 'day',
                retryAfterValue: 86_400,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['retry_after']['value'])->toBe(86_400);
            expect($details['retry_after']['unit'])->toBe('second');
        });

        test('includes HTTP status code in details for basic create method', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::create();

            // Assert
            $details = $exception->getErrorDetails();
            expect($details[0])->toHaveKey('status');
            expect($details[0]['status'])->toBe('429');
        });

        test('includes HTTP status code in details for createWithDetails method', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 100,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 60,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details)->toHaveKey('status');
            expect($details['status'])->toBe('429');
        });

        test('includes error title in details for basic create method', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::create();

            // Assert
            $details = $exception->getErrorDetails();
            expect($details[0])->toHaveKey('title');
            expect($details[0]['title'])->toBe('Too Many Requests');
        });

        test('includes error title in details for createWithDetails method', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 100,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 60,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details)->toHaveKey('title');
            expect($details['title'])->toBe('Too Many Requests');
        });

        test('handles custom detail message with special characters', function (): void {
            // Arrange
            $customDetail = 'API tier limit exceeded: "Premium" users only. See https://example.com/pricing for details.';

            // Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 100,
                used: 100,
                windowValue: 1,
                windowUnit: 'minute',
                retryAfterValue: 60,
                retryAfterUnit: 'second',
                detail: $customDetail,
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['detail'])->toBe($customDetail);
        });

        test('handles very large limit values for high-throughput APIs', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 1_000_000,
                used: 1_000_000,
                windowValue: 1,
                windowUnit: 'hour',
                retryAfterValue: 3_600,
                retryAfterUnit: 'second',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['limit'])->toBe(1_000_000);
            expect($details['used'])->toBe(1_000_000);
        });

        test('handles different time unit combinations for window and retry', function (): void {
            // Arrange & Act
            $exception = TooManyRequestsException::createWithDetails(
                limit: 1_000,
                used: 1_000,
                windowValue: 60,
                windowUnit: 'minute',
                retryAfterValue: 1,
                retryAfterUnit: 'hour',
            );

            // Assert
            $details = $exception->getErrorDetails();
            expect($details['window']['unit'])->toBe('minute');
            expect($details['retry_after']['unit'])->toBe('hour');
            expect($details['window']['unit'])->not->toBe($details['retry_after']['unit']);
        });

        test('preserves all structured data when serialized to array', function (): void {
            // Arrange
            $exception = TooManyRequestsException::createWithDetails(
                limit: 250,
                used: 250,
                windowValue: 5,
                windowUnit: 'minute',
                retryAfterValue: 300,
                retryAfterUnit: 'second',
                detail: 'Per-user rate limit exceeded',
            );

            // Act
            $array = $exception->toArray();

            // Assert
            expect($array['details']['limit'])->toBe(250);
            expect($array['details']['used'])->toBe(250);
            expect($array['details']['window']['value'])->toBe(5);
            expect($array['details']['window']['unit'])->toBe('minute');
            expect($array['details']['retry_after']['value'])->toBe(300);
            expect($array['details']['retry_after']['unit'])->toBe('second');
            expect($array['details']['detail'])->toBe('Per-user rate limit exceeded');
        });
    });
});
