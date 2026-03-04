<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\CallData;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Events\FunctionExecuted;
use Cline\Forrst\Events\RequestValidated;
use Cline\Forrst\Extensions\ExtensionUrn;
use Cline\Forrst\Extensions\RedactExtension;
use Cline\Forrst\Extensions\RedactionMode;

describe('RedactExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::Redact->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:redact');
        });

        test('default mode is full redaction', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act
            $mode = $extension->getMode();

            // Assert
            expect($mode)->toBe(RedactionMode::Full);
        });

        test('FULL_MASK constant is asterisks', function (): void {
            // Assert
            expect(RedactExtension::FULL_MASK)->toBe('***');
        });

        test('DEFAULT_REDACTED_FIELDS contains all categories', function (): void {
            // Assert
            expect(RedactExtension::DEFAULT_REDACTED_FIELDS)
                ->toHaveKey('authentication')
                ->toHaveKey('payment')
                ->toHaveKey('pii')
                ->toHaveKey('contact');
        });

        test('authentication fields include password and token', function (): void {
            // Assert
            $authFields = RedactExtension::DEFAULT_REDACTED_FIELDS['authentication'];
            expect($authFields)->toContain('password')
                ->and($authFields)->toContain('secret')
                ->and($authFields)->toContain('token')
                ->and($authFields)->toContain('api_key');
        });

        test('payment fields include card_number and cvv', function (): void {
            // Assert
            $paymentFields = RedactExtension::DEFAULT_REDACTED_FIELDS['payment'];
            expect($paymentFields)->toContain('card_number')
                ->and($paymentFields)->toContain('cvv')
                ->and($paymentFields)->toContain('account_number');
        });

        test('pii fields include ssn and tax_id', function (): void {
            // Assert
            $piiFields = RedactExtension::DEFAULT_REDACTED_FIELDS['pii'];
            expect($piiFields)->toContain('ssn')
                ->and($piiFields)->toContain('tax_id')
                ->and($piiFields)->toContain('passport_number');
        });

        test('contact fields include email and phone', function (): void {
            // Assert
            $contactFields = RedactExtension::DEFAULT_REDACTED_FIELDS['contact'];
            expect($contactFields)->toContain('email')
                ->and($contactFields)->toContain('phone');
        });

        test('getRedactionFields returns all default fields', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act
            $fields = $extension->getRedactionFields();

            // Assert
            expect($fields)->toContain('password')
                ->and($fields)->toContain('card_number')
                ->and($fields)->toContain('ssn')
                ->and($fields)->toContain('email');
        });

        test('shouldRedact returns true for redactable fields in full mode', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act & Assert
            expect($extension->shouldRedact('password'))->toBeTrue();
            expect($extension->shouldRedact('card_number'))->toBeTrue();
            expect($extension->shouldRedact('ssn'))->toBeTrue();
        });

        test('shouldRedact returns false for non-redactable fields', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act & Assert
            expect($extension->shouldRedact('user_id'))->toBeFalse();
            expect($extension->shouldRedact('name'))->toBeFalse();
            expect($extension->shouldRedact('order_id'))->toBeFalse();
        });

        test('redactField returns full mask in full mode', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act
            $result = $extension->redactField('password', 'mysecretpassword');

            // Assert
            expect($result)->toBe(RedactExtension::FULL_MASK);
        });

        test('getSubscribedEvents returns RequestValidated and FunctionExecuted', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act
            $events = $extension->getSubscribedEvents();

            // Assert
            expect($events)->toHaveKey(RequestValidated::class)
                ->and($events)->toHaveKey(FunctionExecuted::class);
        });
    });

    describe('Partial Redaction', function (): void {
        test('redacts email address in partial mode showing first letter and TLD', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('email', 'john.doe@example.com');

            // Assert
            expect($result)->toBe('j***@***.com');
        });

        test('redacts phone number in partial mode showing last 4 digits', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('phone', '555-123-4567');

            // Assert
            expect($result)->toBe('***-***-4567');
        });

        test('redacts card number in partial mode showing last 4 digits', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('card_number', '4111-1111-1111-1234');

            // Assert
            expect($result)->toBe('****-****-****-1234');
        });

        test('redacts SSN in partial mode showing last 4 digits', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('ssn', '123-45-6789');

            // Assert
            expect($result)->toBe('***-**-6789');
        });

        test('redacts full name in partial mode showing first letter of each word', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('name', 'John Michael Doe');

            // Assert
            expect($result)->toBe('J*** M*** D***');
        });

        test('redacts cardholder name in partial mode using name pattern', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('cardholder', 'Jane Smith');

            // Assert
            expect($result)->toBe('J*** S***');
        });

        test('partial mode uses default pattern for unknown fields with long values', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('unknown_field', 'testvalue12345');

            // Assert - Default partial shows last 4 chars (14 chars total, so 10 asterisks + 4 chars)
            expect($result)->toBe('**********2345');
        });

        test('partial mode handles phone number with various formats', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('phone', '(555) 123-4567');

            // Assert
            expect($result)->toBe('***-***-4567');
        });

        test('partial mode handles card number with spaces', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('card_number', '4111 1111 1111 1234');

            // Assert
            expect($result)->toBe('****-****-****-1234');
        });

        test('partial mode handles email with single character username', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('email', 'j@example.com');

            // Assert
            expect($result)->toBe('j***@***.com');
        });

        test('partial mode handles name with extra whitespace', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('name', '  John   Doe  ');

            // Assert
            expect($result)->toBe('J*** D***');
        });
    });

    describe('Edge Cases', function (): void {
        test('redactField handles non-string values', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act
            $result = $extension->redactField('password', 12_345);

            // Assert
            expect($result)->toBe(RedactExtension::FULL_MASK);
        });

        test('redactField handles empty string', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act
            $result = $extension->redactField('password', '');

            // Assert
            expect($result)->toBe(RedactExtension::FULL_MASK);
        });

        test('redactField handles very short strings', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act
            $result = $extension->redactField('password', 'ab');

            // Assert
            expect($result)->toBe(RedactExtension::FULL_MASK);
        });

        test('constructor accepts custom unredacted scopes', function (): void {
            // Arrange
            $customScopes = ['admin:full_access', 'pii:read:all'];

            // Act
            $extension = new RedactExtension($customScopes);

            // Assert - Extension should be created without errors
            expect($extension)->toBeInstanceOf(RedactExtension::class);
        });

        test('constructor accepts custom default policy', function (): void {
            // Arrange
            $extension = new RedactExtension(
                unredactedScopes: ['admin:full_access'],
                defaultPolicy: 'custom_policy',
            );

            // Assert - Extension should be created without errors
            expect($extension)->toBeInstanceOf(RedactExtension::class);
        });

        test('shouldRedact is case sensitive', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act & Assert
            expect($extension->shouldRedact('password'))->toBeTrue();
            expect($extension->shouldRedact('PASSWORD'))->toBeFalse();
            expect($extension->shouldRedact('Password'))->toBeFalse();
        });
    });

    describe('Recursive Redaction', function (): void {
        test('onFunctionExecuted redacts sensitive fields in flat array response', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Redact->value, ['mode' => 'full']);
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-123',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
                extensions: [$extensionData],
            );
            $response = ResponseData::success([
                'user_id' => '12345',
                'password' => 'secret123',
                'email' => 'user@example.com',
            ], 'req-123');
            $event = new FunctionExecuted($request, $extensionData, $response);

            // Trigger request validation first
            $requestEvent = new RequestValidated($request);
            $extension->onRequestValidated($requestEvent);

            // Act
            $extension->onFunctionExecuted($event);

            $result = $event->getResponse();

            // Assert
            expect($result->result['user_id'])->toBe('12345')
                ->and($result->result['password'])->toBe(RedactExtension::FULL_MASK)
                ->and($result->result['email'])->toBe(RedactExtension::FULL_MASK)
                ->and($result->extensions)->toHaveCount(1)
                ->and($result->extensions[0]->data['mode'])->toBe('full');
        });

        test('onFunctionExecuted redacts sensitive fields in nested array response', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Redact->value, ['mode' => 'full']);
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-456',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
                extensions: [$extensionData],
            );
            $response = ResponseData::success([
                'user' => [
                    'id' => '789',
                    'password' => 'mypassword',
                    'profile' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                ],
            ], 'req-456');
            $event = new FunctionExecuted($request, $extensionData, $response);

            // Trigger request validation first
            $requestEvent = new RequestValidated($request);
            $extension->onRequestValidated($requestEvent);

            // Act
            $extension->onFunctionExecuted($event);

            $result = $event->getResponse();

            // Assert
            expect($result->result['user']['id'])->toBe('789')
                ->and($result->result['user']['password'])->toBe(RedactExtension::FULL_MASK)
                ->and($result->result['user']['profile']['name'])->toBe('John Doe')
                ->and($result->result['user']['profile']['email'])->toBe(RedactExtension::FULL_MASK);
        });

        test('onFunctionExecuted tracks redacted field paths in nested structures', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Redact->value, ['mode' => 'full']);
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-789',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
                extensions: [$extensionData],
            );
            $response = ResponseData::success([
                'user' => [
                    'password' => 'secret',
                    'settings' => [
                        'api_key' => 'key123',
                    ],
                ],
            ], 'req-789');
            $event = new FunctionExecuted($request, $extensionData, $response);

            // Trigger request validation first
            $requestEvent = new RequestValidated($request);
            $extension->onRequestValidated($requestEvent);

            // Act
            $extension->onFunctionExecuted($event);

            $result = $event->getResponse();

            // Assert
            expect($result->extensions[0]->data['redacted_fields'])->toContain('user.password')
                ->and($result->extensions[0]->data['redacted_fields'])->toContain('user.settings.api_key');
        });

        test('onFunctionExecuted handles array of objects with sensitive fields', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Redact->value, ['mode' => 'full']);
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-list',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
                extensions: [$extensionData],
            );
            $response = ResponseData::success([
                'users' => [
                    ['id' => '1', 'email' => 'user1@example.com'],
                    ['id' => '2', 'email' => 'user2@example.com'],
                ],
            ], 'req-list');
            $event = new FunctionExecuted($request, $extensionData, $response);

            // Trigger request validation first
            $requestEvent = new RequestValidated($request);
            $extension->onRequestValidated($requestEvent);

            // Act
            $extension->onFunctionExecuted($event);

            $result = $event->getResponse();

            // Assert
            expect($result->result['users'][0]['email'])->toBe(RedactExtension::FULL_MASK)
                ->and($result->result['users'][1]['email'])->toBe(RedactExtension::FULL_MASK)
                ->and($result->result['users'][0]['id'])->toBe('1')
                ->and($result->result['users'][1]['id'])->toBe('2');
        });

        test('onFunctionExecuted does not redact when mode is None by manually setting state', function (): void {
            // Arrange - Manually set mode to None via reflection
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::None;
            $stateProperty->setValue($extension, $state);

            $extensionData = ExtensionData::request(ExtensionUrn::Redact->value, ['mode' => 'none']);
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-none',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
                extensions: [$extensionData],
            );
            $response = ResponseData::success([
                'password' => 'secret123',
                'email' => 'user@example.com',
            ], 'req-none');
            $event = new FunctionExecuted($request, $extensionData, $response);

            // Act
            $extension->onFunctionExecuted($event);
            $result = $event->getResponse();

            // Assert
            expect($result->result['password'])->toBe('secret123')
                ->and($result->result['email'])->toBe('user@example.com')
                ->and($result->extensions[0]->data['mode'])->toBe('none')
                ->and(isset($result->extensions[0]->data['redacted_fields']))->toBeFalse();
        });

        test('onFunctionExecuted handles null response result gracefully', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $extensionData = ExtensionData::request(ExtensionUrn::Redact->value, ['mode' => 'full']);
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-null',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
                extensions: [$extensionData],
            );
            $response = ResponseData::success(null, 'req-null');
            $event = new FunctionExecuted($request, $extensionData, $response);

            // Trigger request validation first
            $requestEvent = new RequestValidated($request);
            $extension->onRequestValidated($requestEvent);

            // Act
            $extension->onFunctionExecuted($event);

            $result = $event->getResponse();

            // Assert
            expect($result->result)->toBeNull()
                ->and(isset($result->extensions[0]->data['redacted_fields']))->toBeFalse();
        });

        test('onFunctionExecuted skips processing when extension not present in request', function (): void {
            // Arrange
            $extension = new RedactExtension();
            // Create a dummy extension data for the event (different URN)
            $dummyExtension = ExtensionData::request('urn:cline:forrst:ext:other', []);
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-no-ext',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
            );
            $response = ResponseData::success(['password' => 'secret'], 'req-no-ext');
            $event = new FunctionExecuted($request, $dummyExtension, $response);

            // Act
            $extension->onFunctionExecuted($event);
            $result = $event->getResponse();

            // Assert - Password should NOT be redacted since redact extension not present
            expect($result->result['password'])->toBe('secret')
                ->and($result->extensions)->toBeNull();
        });
    });

    describe('Authorization and Access Control', function (): void {
        test('onRequestValidated denies unredacted access by default', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-deny',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
                extensions: [ExtensionData::request(ExtensionUrn::Redact->value, ['mode' => 'none'])],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            expect($event->getResponse())->not->toBeNull()
                ->and($event->getResponse()->errors)->toHaveCount(1)
                ->and($event->getResponse()->errors[0]->code)->toBe('FORBIDDEN')
                ->and($event->getResponse()->errors[0]->message)->toContain('elevated permissions');
        });

        test('onRequestValidated denies unredacted access even with purpose provided', function (): void {
            // Arrange - Since isUnredactedAccessAllowed is protected and returns false by default
            $extension = new RedactExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-allowed',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
                extensions: [ExtensionData::request(ExtensionUrn::Redact->value, [
                    'mode' => 'none',
                    'purpose' => 'audit investigation',
                ])],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert - Default implementation denies access
            expect($event->getResponse())->not->toBeNull()
                ->and($event->getResponse()->errors)->toHaveCount(1)
                ->and($event->getResponse()->errors[0]->code)->toBe('FORBIDDEN');
        });

        test('onRequestValidated uses custom fields when provided', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-custom',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
                extensions: [ExtensionData::request(ExtensionUrn::Redact->value, [
                    'mode' => 'full',
                    'fields' => ['custom_secret', 'custom_token'],
                ])],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            expect($extension->getRedactionFields())->toBe(['custom_secret', 'custom_token'])
                ->and($extension->shouldRedact('custom_secret'))->toBeTrue()
                ->and($extension->shouldRedact('password'))->toBeFalse();
        });

        test('onRequestValidated falls back to Full mode for invalid mode value', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-invalid',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
                extensions: [ExtensionData::request(ExtensionUrn::Redact->value, ['mode' => 'invalid_mode'])],
            );
            $event = new RequestValidated($request);

            // Act
            $extension->onRequestValidated($event);

            // Assert
            expect($extension->getMode())->toBe(RedactionMode::Full);
        });

        test('onRequestValidated skips processing when extension not present', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $request = new RequestObjectData(
                protocol: ProtocolData::forrst(),
                id: 'req-no-ext',
                call: new CallData(function: 'urn:cline:forrst:fn:test:function'),
            );
            $event = new RequestValidated($request);
            $originalMode = $extension->getMode();

            // Act
            $extension->onRequestValidated($event);

            // Assert
            expect($extension->getMode())->toBe($originalMode)
                ->and($event->getResponse())->toBeNull();
        });

        test('constructor accepts custom unredacted scopes array', function (): void {
            // Arrange
            $customScopes = ['pii:read:all', 'admin:full_access'];

            // Act
            $extension = new RedactExtension(unredactedScopes: $customScopes);

            // Assert - Extension should be created successfully
            expect($extension)->toBeInstanceOf(RedactExtension::class);
        });
    });

    describe('Partial Redaction Edge Cases', function (): void {
        test('partial email redaction handles malformed email without @ symbol', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('email', 'notanemail');

            // Assert
            expect($result)->toBe(RedactExtension::FULL_MASK);
        });

        test('partial email redaction handles empty username', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('email', '@example.com');

            // Assert
            expect($result)->toBe('***@***.com');
        });

        test('partial phone redaction handles very short phone numbers', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('phone', '123');

            // Assert
            expect($result)->toBe(RedactExtension::FULL_MASK);
        });

        test('partial card number redaction handles very short card numbers', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('card_number', '123');

            // Assert
            expect($result)->toBe(RedactExtension::FULL_MASK);
        });

        test('partial SSN redaction handles very short SSN', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('ssn', '12');

            // Assert
            expect($result)->toBe(RedactExtension::FULL_MASK);
        });

        test('partial name redaction handles empty name', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('name', '');

            // Assert - partialName returns empty string for empty input, which becomes implode('', [])
            expect($result)->toBe('');
        });

        test('partial name redaction handles single character name', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('name', 'J');

            // Assert
            expect($result)->toBe('J***');
        });

        test('partial redaction for short values defaults to full mask', function (): void {
            // Arrange
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::Partial;
            $stateProperty->setValue($extension, $state);

            // Act
            $result = $extension->redactField('unknown_field', 'abc');

            // Assert
            expect($result)->toBe(RedactExtension::FULL_MASK);
        });
    });

    describe('Sad Paths', function (): void {
        test('redactField masks unknown fields with default pattern', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act - unknown_field has no special pattern
            $result = $extension->redactField('unknown_field', 'testvalue12');

            // Assert - Should still return mask
            expect($result)->toBe(RedactExtension::FULL_MASK);
        });

        test('isErrorFatal returns true for security-critical extension', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act
            $isFatal = $extension->isErrorFatal();

            // Assert
            expect($isFatal)->toBeTrue();
        });

        test('getMode returns full before request processing', function (): void {
            // Arrange
            $extension = new RedactExtension();

            // Act
            $mode = $extension->getMode();

            // Assert
            expect($mode)->toBe(RedactionMode::Full);
        });

        test('shouldRedact returns false when mode is None via reflection', function (): void {
            // Arrange - Manually set mode to None via reflection
            $extension = new RedactExtension();
            $reflection = new ReflectionClass($extension);
            $stateProperty = $reflection->getProperty('state');

            $state = $stateProperty->getValue($extension);
            $state['mode'] = RedactionMode::None;
            $stateProperty->setValue($extension, $state);

            // Act & Assert
            expect($extension->shouldRedact('password'))->toBeFalse()
                ->and($extension->shouldRedact('email'))->toBeFalse();
        });
    });

    describe('Redaction Mode Enum', function (): void {
        test('RedactionMode has three valid modes', function (): void {
            // Assert
            expect(RedactionMode::cases())->toHaveCount(3);
        });

        test('RedactionMode Full value is full', function (): void {
            // Assert
            expect(RedactionMode::Full->value)->toBe('full');
        });

        test('RedactionMode Partial value is partial', function (): void {
            // Assert
            expect(RedactionMode::Partial->value)->toBe('partial');
        });

        test('RedactionMode None value is none', function (): void {
            // Assert
            expect(RedactionMode::None->value)->toBe('none');
        });

        test('RedactionMode can be created from string', function (): void {
            // Act & Assert
            expect(RedactionMode::tryFrom('full'))->toBe(RedactionMode::Full);
            expect(RedactionMode::tryFrom('partial'))->toBe(RedactionMode::Partial);
            expect(RedactionMode::tryFrom('none'))->toBe(RedactionMode::None);
        });

        test('RedactionMode returns null for invalid string', function (): void {
            // Act
            $result = RedactionMode::tryFrom('invalid');

            // Assert
            expect($result)->toBeNull();
        });
    });
});
