<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Extensions\DryRunExtension;
use Cline\Forrst\Extensions\ExtensionUrn;

describe('DryRunExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::DryRun->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:dry-run');
        });

        test('isEnabled returns true when enabled option is true', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $options = ['enabled' => true];

            // Act
            $result = $extension->isEnabled($options);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isEnabled returns false when enabled option is false', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $options = ['enabled' => false];

            // Act
            $result = $extension->isEnabled($options);

            // Assert
            expect($result)->toBeFalse();
        });

        test('shouldIncludeDiff returns true when include_diff is true', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $options = ['include_diff' => true];

            // Act
            $result = $extension->shouldIncludeDiff($options);

            // Assert
            expect($result)->toBeTrue();
        });

        test('shouldIncludeSideEffects returns true when include_side_effects is true', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $options = ['include_side_effects' => true];

            // Act
            $result = $extension->shouldIncludeSideEffects($options);

            // Assert
            expect($result)->toBeTrue();
        });

        test('buildValidResponse creates successful dry-run response', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0001',
                call: new CallData(function: 'updateUser', arguments: ['id' => 123]),
            );
            $wouldAffect = [
                ['type' => 'user', 'id' => 123, 'action' => 'update'],
            ];

            // Act
            $response = $extension->buildValidResponse($request, $wouldAffect);

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->id)->toBe('01JFEX0001')
                ->and($response->result)->toBeNull()
                ->and($response->extensions)->toHaveCount(1);

            $ext = $response->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::DryRun->value)
                ->and($ext->data)->toHaveKey('valid', true)
                ->and($ext->data)->toHaveKey('would_affect', $wouldAffect);
        });

        test('buildValidResponse includes diff when provided', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0002',
                call: new CallData(function: 'updateUser'),
            );
            $diff = [
                'before' => ['status' => 'active'],
                'after' => ['status' => 'inactive'],
            ];

            // Act
            $response = $extension->buildValidResponse($request, [], $diff);

            // Assert
            $ext = $response->extensions[0];
            expect($ext->data)->toHaveKey('diff', $diff);
        });

        test('buildValidResponse includes side effects when provided', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0003',
                call: new CallData(function: 'deleteUser'),
            );
            $sideEffects = [
                ['type' => 'email', 'event' => 'user.deleted', 'count' => 1],
            ];

            // Act
            $response = $extension->buildValidResponse($request, [], null, $sideEffects);

            // Assert
            $ext = $response->extensions[0];
            expect($ext->data)->toHaveKey('side_effects', $sideEffects);
        });

        test('buildValidResponse includes estimated duration when provided', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0004',
                call: new CallData(function: 'batchUpdate'),
            );
            $estimatedDuration = ['value' => 5_000, 'unit' => 'millisecond'];

            // Act
            $response = $extension->buildValidResponse($request, [], null, null, $estimatedDuration);

            // Assert
            $ext = $response->extensions[0];
            expect($ext->data)->toHaveKey('estimated_duration', $estimatedDuration);
        });

        test('buildInvalidResponse creates validation error response', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0005',
                call: new CallData(function: 'createUser'),
            );
            $validationErrors = [
                ['field' => 'email', 'code' => 'INVALID_EMAIL', 'message' => 'Invalid email format'],
            ];

            // Act
            $response = $extension->buildInvalidResponse($request, $validationErrors);

            // Assert
            expect($response)->toBeInstanceOf(ResponseData::class)
                ->and($response->id)->toBe('01JFEX0005');

            $ext = $response->extensions[0];
            expect($ext->data)->toHaveKey('valid', false)
                ->and($ext->data)->toHaveKey('validation_errors', $validationErrors);
        });

        test('buildValidationError creates properly formatted error entry', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $error = $extension->buildValidationError('user.email', 'REQUIRED', 'Email is required');

            // Assert
            expect($error)->toBe([
                'field' => 'user.email',
                'code' => 'REQUIRED',
                'message' => 'Email is required',
            ]);
        });

        test('buildWouldAffect creates resource entry with string ID', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $entry = $extension->buildWouldAffect('user', 'uuid-123', 'delete');

            // Assert
            expect($entry)->toBe([
                'type' => 'user',
                'id' => 'uuid-123',
                'action' => 'delete',
            ]);
        });

        test('buildWouldAffect creates resource entry with integer ID', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $entry = $extension->buildWouldAffect('post', 456, 'update');

            // Assert
            expect($entry)->toBe([
                'type' => 'post',
                'id' => 456,
                'action' => 'update',
            ]);
        });

        test('buildWouldAffectCount creates count-based entry', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $entry = $extension->buildWouldAffectCount('comment', 25, 'delete');

            // Assert
            expect($entry)->toBe([
                'type' => 'comment',
                'count' => 25,
                'action' => 'delete',
            ]);
        });

        test('buildSideEffect creates side effect entry', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $entry = $extension->buildSideEffect('webhook', 'order.created', 3);

            // Assert
            expect($entry)->toBe([
                'type' => 'webhook',
                'event' => 'order.created',
                'count' => 3,
            ]);
        });

        test('buildSideEffect uses default count of 1', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $entry = $extension->buildSideEffect('email', 'user.welcome');

            // Assert
            expect($entry)->toHaveKey('count', 1);
        });
    });

    describe('Edge Cases', function (): void {
        test('isEnabled returns false when enabled option is missing', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $options = [];

            // Act
            $result = $extension->isEnabled($options);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isEnabled returns false when options is null', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $result = $extension->isEnabled(null);

            // Assert
            expect($result)->toBeFalse();
        });

        test('shouldIncludeDiff returns false when option is missing', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $options = [];

            // Act
            $result = $extension->shouldIncludeDiff($options);

            // Assert
            expect($result)->toBeFalse();
        });

        test('shouldIncludeSideEffects returns false when option is missing', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $options = [];

            // Act
            $result = $extension->shouldIncludeSideEffects($options);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isEnabled returns false for non-boolean true values', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act & Assert
            expect($extension->isEnabled(['enabled' => 1]))->toBeFalse();
            expect($extension->isEnabled(['enabled' => 'true']))->toBeFalse();
            expect($extension->isEnabled(['enabled' => 'yes']))->toBeFalse();
        });

        test('buildValidResponse handles empty would_affect array', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0006',
                call: new CallData(function: 'readOnlyOp'),
            );

            // Act
            $response = $extension->buildValidResponse($request);

            // Assert
            $ext = $response->extensions[0];
            expect($ext->data['would_affect'])->toBe([]);
        });

        test('buildValidResponse with all optional parameters', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0007',
                call: new CallData(function: 'complexOperation'),
            );
            $wouldAffect = [
                ['type' => 'user', 'id' => 1, 'action' => 'update'],
                ['type' => 'post', 'count' => 5, 'action' => 'delete'],
            ];
            $diff = ['before' => ['count' => 10], 'after' => ['count' => 5]];
            $sideEffects = [
                ['type' => 'email', 'event' => 'notification', 'count' => 2],
                ['type' => 'webhook', 'event' => 'sync', 'count' => 1],
            ];
            $estimatedDuration = ['value' => 2_500, 'unit' => 'millisecond'];

            // Act
            $response = $extension->buildValidResponse(
                $request,
                $wouldAffect,
                $diff,
                $sideEffects,
                $estimatedDuration,
            );

            // Assert
            $ext = $response->extensions[0];
            expect($ext->data)->toHaveKey('valid', true)
                ->and($ext->data)->toHaveKey('would_affect', $wouldAffect)
                ->and($ext->data)->toHaveKey('diff', $diff)
                ->and($ext->data)->toHaveKey('side_effects', $sideEffects)
                ->and($ext->data)->toHaveKey('estimated_duration', $estimatedDuration);
        });

        test('buildValidationError handles special characters in field names', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $error = $extension->buildValidationError(
                'user.settings.notifications[0].email',
                'INVALID_FORMAT',
                'Invalid email in nested array',
            );

            // Assert
            expect($error['field'])->toBe('user.settings.notifications[0].email')
                ->and($error['code'])->toBe('INVALID_FORMAT')
                ->and($error['message'])->toBe('Invalid email in nested array');
        });

        test('buildInvalidResponse with multiple validation errors', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0008',
                call: new CallData(function: 'createUser'),
            );
            $validationErrors = [
                ['field' => 'email', 'code' => 'REQUIRED', 'message' => 'Email is required'],
                ['field' => 'name', 'code' => 'TOO_SHORT', 'message' => 'Name too short'],
                ['field' => 'age', 'code' => 'OUT_OF_RANGE', 'message' => 'Age must be positive'],
            ];

            // Act
            $response = $extension->buildInvalidResponse($request, $validationErrors);

            // Assert
            $ext = $response->extensions[0];
            expect($ext->data['validation_errors'])->toHaveCount(3)
                ->and($ext->data['validation_errors'])->toBe($validationErrors);
        });

        test('buildWouldAffectCount handles zero count', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $entry = $extension->buildWouldAffectCount('notification', 0, 'send');

            // Assert
            expect($entry['count'])->toBe(0);
        });

        test('buildSideEffect handles very large count', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act
            $entry = $extension->buildSideEffect('log_entry', 'batch.processed', 1_000_000);

            // Assert
            expect($entry['count'])->toBe(1_000_000);
        });
    });

    describe('Sad Paths', function (): void {
        test('buildValidResponse preserves request ID in response', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'custom-request-id-12345',
                call: new CallData(function: 'test'),
            );

            // Act
            $response = $extension->buildValidResponse($request);

            // Assert
            expect($response->id)->toBe('custom-request-id-12345');
        });

        test('buildInvalidResponse preserves request ID in error response', function (): void {
            // Arrange
            $extension = new DryRunExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'error-request-id-999',
                call: new CallData(function: 'test'),
            );

            // Act
            $response = $extension->buildInvalidResponse($request, []);

            // Assert
            expect($response->id)->toBe('error-request-id-999');
        });

        test('isEnabled strictly checks for boolean true', function (): void {
            // Arrange
            $extension = new DryRunExtension();

            // Act & Assert - truthy values that are not boolean true
            expect($extension->isEnabled(['enabled' => 1]))->toBeFalse();
            expect($extension->isEnabled(['enabled' => '1']))->toBeFalse();
            expect($extension->isEnabled(['enabled' => 'true']))->toBeFalse();
            expect($extension->isEnabled(['enabled' => []]))->toBeFalse();
        });
    });
});
