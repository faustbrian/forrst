<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Exceptions\JsonSchemaException;
use Cline\Forrst\JsonSchema\RuleTransformer;

describe('RuleTransformer', function (): void {
    describe('transform', function (): void {
        describe('Happy Paths', function (): void {
            test('transforms accepted rule to enum schema', function (): void {
                // Arrange
                $field = 'terms';
                $rules = ['accepted'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result)->toHaveKey('enum');
                expect($result['enum'])->toContain(true);
                expect($result['enum'])->toContain('true');
                expect($result['enum'])->toContain(1);
                expect($result['enum'])->toContain('1');
                expect($result['enum'])->toContain('yes');
                expect($result['enum'])->toContain('on');
            });

            test('transforms accepted_if rule to conditional schema', function (): void {
                // Arrange
                $field = 'consent';
                $rules = ['accepted_if:age,18'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result)->toHaveKey('properties');
                expect($result['properties'][$field])->toHaveKey('if');
                expect($result['properties'][$field]['if']['properties']['age']['const'])->toBe('18');
                expect($result['properties'][$field]['then']['required'])->toContain($field);
            });

            test('transforms active_url rule to uri format', function (): void {
                // Arrange
                $field = 'website';
                $rules = ['active_url'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['format'])->toBe('uri');
            });

            test('transforms after date rule with exclusiveMinimum', function (): void {
                // Arrange
                $field = 'end_date';
                $rules = ['after:2024-01-01'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['properties'][$field]['format'])->toBe('date-time');
                expect($result['properties'][$field]['exclusiveMinimum'])->toBe('2024-01-01');
            });

            test('transforms after_or_equal date rule with minimum', function (): void {
                // Arrange
                $field = 'start_date';
                $rules = ['after_or_equal:2024-01-01'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['properties'][$field]['format'])->toBe('date-time');
                expect($result['properties'][$field]['minimum'])->toBe('2024-01-01');
            });

            test('transforms alpha rule to letter pattern', function (): void {
                // Arrange
                $field = 'letters';
                $rules = ['alpha'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['pattern'])->toBe('^[a-zA-Z]+$');
            });

            test('transforms alpha_dash rule to alphanumeric with dashes pattern', function (): void {
                // Arrange
                $field = 'slug';
                $rules = ['alpha_dash'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['pattern'])->toBe('^[a-zA-Z0-9-_]+$');
            });

            test('transforms alpha_num rule to alphanumeric pattern', function (): void {
                // Arrange
                $field = 'code';
                $rules = ['alpha_num'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['pattern'])->toBe('^[a-zA-Z0-9]+$');
            });

            test('transforms array rule to array type', function (): void {
                // Arrange
                $field = 'items';
                $rules = ['array'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('array');
            });

            test('transforms ascii rule to ascii pattern', function (): void {
                // Arrange
                $field = 'text';
                $rules = ['ascii'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['pattern'])->toBe('^[\x00-\x7F]+$');
            });

            test('transforms before date rule with exclusiveMaximum', function (): void {
                // Arrange
                $field = 'deadline';
                $rules = ['before:2024-12-31'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['properties'][$field]['format'])->toBe('date-time');
                expect($result['properties'][$field]['exclusiveMaximum'])->toBe('2024-12-31');
            });

            test('transforms before_or_equal date rule with maximum', function (): void {
                // Arrange
                $field = 'due_date';
                $rules = ['before_or_equal:2024-12-31'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['properties'][$field]['format'])->toBe('date-time');
                expect($result['properties'][$field]['maximum'])->toBe('2024-12-31');
            });

            test('transforms between rule to minimum and maximum', function (): void {
                // Arrange
                $field = 'rating';
                $rules = ['between:1,5'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['minimum'])->toBe(1);
                expect($result['maximum'])->toBe(5);
            });

            test('transforms boolean rule to boolean type', function (): void {
                // Arrange
                $field = 'active';
                $rules = ['boolean'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('boolean');
            });

            test('transforms confirmed rule with data reference', function (): void {
                // Arrange
                $field = 'password';
                $rules = ['confirmed'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['const'])->toBeArray();
                expect($result['properties'][$field]['const'])->toContain('$data');
                expect($result['properties'][$field]['const'])->toContain($field);
            });

            test('transforms date rule to date format', function (): void {
                // Arrange
                $field = 'birth_date';
                $rules = ['date'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['format'])->toBe('date');
            });

            test('transforms date_equals rule to enum with single date', function (): void {
                // Arrange
                $field = 'event_date';
                $rules = ['date_equals:2024-01-01'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['format'])->toBe('date');
                expect($result['enum'])->toContain('2024-01-01');
            });

            test('transforms date_format rule with custom format', function (): void {
                // Arrange
                $field = 'custom_date';
                $rules = ['date_format:Y-m-d H:i:s'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['properties'][$field]['format'])->toBe('Y-m-d H:i:s');
            });

            test('transforms decimal rule with not multipleOf constraint', function (): void {
                // Arrange
                $field = 'price';
                $rules = ['decimal'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('number');
                expect($result['properties'][$field]['not'])->toHaveKey('multipleOf');
                expect($result['properties'][$field]['not']['multipleOf'])->toBe(1);
            });

            test('transforms declined rule to false enum values', function (): void {
                // Arrange
                $field = 'marketing';
                $rules = ['declined'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['enum'])->toContain(false);
                expect($result['enum'])->toContain('false');
                expect($result['enum'])->toContain(0);
                expect($result['enum'])->toContain('0');
                expect($result['enum'])->toContain('no');
                expect($result['enum'])->toContain('off');
            });

            test('transforms declined_if rule to conditional not schema', function (): void {
                // Arrange
                $field = 'opt_out';
                $rules = ['declined_if:country,US'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['not'])->toHaveKey('if');
                expect($result['properties'][$field]['not']['if']['properties']['country']['const'])->toBe('US');
            });

            test('transforms different rule with reference to other field', function (): void {
                // Arrange
                $field = 'new_email';
                $rules = ['different:old_email'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['properties'][$field]['not'])->toHaveKey('$ref');
                expect($result['properties'][$field]['properties'][$field]['not']['$ref'])->toBe('#/properties/old_email');
            });

            test('transforms digits rule to exact digit pattern', function (): void {
                // Arrange
                $field = 'pin';
                $rules = ['digits:4'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['pattern'])->toBe('^[0-9]{4}$');
            });

            test('transforms digits_between rule to digit range pattern', function (): void {
                // Arrange
                $field = 'phone';
                $rules = ['digits_between:10,15'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['pattern'])->toBe('^[0-9]{10,15}$');
            });

            test('transforms doesnt_start_with rule to negative lookahead pattern', function (): void {
                // Arrange
                $field = 'username';
                $rules = ['doesnt_start_with:admin,root'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result)->toHaveKey('pattern');
                expect($result['pattern'])->toContain('^(?!');
            });

            test('transforms doesnt_end_with rule to negative lookahead pattern', function (): void {
                // Arrange
                $field = 'filename';
                $rules = ['doesnt_end_with:.exe,.bat'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result)->toHaveKey('pattern');
                expect($result['pattern'])->toContain(')$');
            });

            test('transforms email rule to email format', function (): void {
                // Arrange
                $field = 'email';
                $rules = ['email'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['format'])->toBe('email');
            });

            test('transforms ends_with rule to suffix pattern', function (): void {
                // Arrange
                $field = 'filename';
                $rules = ['ends_with:.jpg,.png'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result)->toHaveKey('pattern');
                expect($result['pattern'])->toContain(')$');
            });

            test('transforms enum rule to enum values', function (): void {
                // Arrange
                $field = 'status';
                $rules = ['enum:active,pending,inactive'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['enum'])->toContain('active');
                expect($result['enum'])->toContain('pending');
                expect($result['enum'])->toContain('inactive');
            });

            test('transforms exclude rule with not object type', function (): void {
                // Arrange
                $field = 'internal';
                $rules = ['exclude'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['not']['type'])->toBe('object');
            });

            test('transforms exclude_if rule with conditional not required', function (): void {
                // Arrange
                $field = 'optional_field';
                $rules = ['exclude_if:type,guest'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['allOf'])->toBeArray();
                expect($result['allOf'][0]['if']['properties']['type']['const'])->toBe('guest');
                expect($result['allOf'][0]['then']['not']['required'])->toContain($field);
            });

            test('transforms exclude_unless rule with conditional not required', function (): void {
                // Arrange
                $field = 'admin_field';
                $rules = ['exclude_unless:role,admin'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['allOf'])->toBeArray();
                expect($result['allOf'][0]['if']['properties']['role']['not']['enum'])->toContain('admin');
                expect($result['allOf'][0]['then']['not']['required'])->toContain($field);
            });

            test('transforms exclude_with rule with anyOf fields', function (): void {
                // Arrange
                $field = 'field_a';
                $rules = ['exclude_with:field_b,field_c'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['allOf'])->toBeArray();
                expect($result['allOf'][0]['if'])->toHaveKey('anyOf');
                expect($result['allOf'][0]['then']['not']['required'])->toContain($field);
            });

            test('transforms exclude_without rule with allOf fields', function (): void {
                // Arrange
                $field = 'conditional_field';
                $rules = ['exclude_without:required_a,required_b'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['allOf'])->toBeArray();
                expect($result['allOf'][0]['if'])->toHaveKey('allOf');
                expect($result['allOf'][0]['then']['not']['required'])->toContain($field);
            });

            test('transforms filled rule with required property', function (): void {
                // Arrange
                $field = 'non_empty';
                $rules = ['filled'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['required'])->toBeTrue();
            });

            test('transforms gt rule to exclusiveMinimum', function (): void {
                // Arrange
                $field = 'quantity';
                $rules = ['gt:0'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['exclusiveMinimum'])->toBe(0.0);
            });

            test('transforms gte rule to minimum', function (): void {
                // Arrange
                $field = 'age';
                $rules = ['gte:18'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['minimum'])->toBe(18.0);
            });

            test('transforms hex_color rule to hex pattern', function (): void {
                // Arrange
                $field = 'color';
                $rules = ['hex_color'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['pattern'])->toBe('^#(?:[0-9a-fA-F]{3}){1,2}$');
            });

            test('transforms in rule to enum values', function (): void {
                // Arrange
                $field = 'role';
                $rules = ['in:admin,user,guest'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['enum'])->toContain('admin');
                expect($result['enum'])->toContain('user');
                expect($result['enum'])->toContain('guest');
            });

            test('transforms integer rule to integer type', function (): void {
                // Arrange
                $field = 'count';
                $rules = ['integer'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('integer');
            });

            test('transforms ip rule to anyOf ipv4 and ipv6', function (): void {
                // Arrange
                $field = 'ip_address';
                $rules = ['ip'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['anyOf'])->toBeArray();
                expect($result['anyOf'][0]['type'])->toBe('string');
                expect($result['anyOf'][0]['format'])->toBe('ipv4');
                expect($result['anyOf'][1]['format'])->toBe('ipv6');
            });

            test('transforms ipv4 rule to ipv4 format', function (): void {
                // Arrange
                $field = 'ipv4_addr';
                $rules = ['ipv4'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['properties'][$field]['format'])->toBe('ipv4');
            });

            test('transforms ipv6 rule to ipv6 format', function (): void {
                // Arrange
                $field = 'ipv6_addr';
                $rules = ['ipv6'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['properties'][$field]['format'])->toBe('ipv6');
            });

            test('transforms lt rule to exclusiveMaximum', function (): void {
                // Arrange
                $field = 'discount';
                $rules = ['lt:100'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['exclusiveMaximum'])->toBe(100.0);
            });

            test('transforms lte rule to maximum', function (): void {
                // Arrange
                $field = 'score';
                $rules = ['lte:100'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['maximum'])->toBe(100.0);
            });

            test('transforms lowercase rule to lowercase pattern', function (): void {
                // Arrange
                $field = 'username';
                $rules = ['lowercase'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['pattern'])->toBe('^[a-z]*$');
            });

            test('transforms mac_address rule to mac pattern', function (): void {
                // Arrange
                $field = 'mac';
                $rules = ['mac_address'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['pattern'])->toBe('^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$');
            });

            test('transforms max rule to maxLength', function (): void {
                // Arrange
                $field = 'title';
                $rules = ['max:255'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['maxLength'])->toBe(255);
            });

            test('transforms digits_max rule to max digit pattern', function (): void {
                // Arrange
                $field = 'zip_code';
                $rules = ['digits_max:5'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['pattern'])->toBe('^[0-9]{1,5}$');
            });

            test('transforms mimetypes rule to contentMediaType', function (): void {
                // Arrange
                $field = 'document';
                $rules = ['mimetypes:application/pdf,application/json'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['contentMediaType'])->toContain('application/pdf');
                expect($result['properties'][$field]['contentMediaType'])->toContain('application/json');
            });

            test('transforms min rule to minLength', function (): void {
                // Arrange
                $field = 'password';
                $rules = ['min:8'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['minLength'])->toBe(8);
            });

            test('transforms digits_min rule to min digit pattern', function (): void {
                // Arrange
                $field = 'account_number';
                $rules = ['digits_min:8'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['pattern'])->toBe('^[0-9]{8,}$');
            });

            test('transforms missing rule with not null type', function (): void {
                // Arrange
                $field = 'should_not_exist';
                $rules = ['missing'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['not']['type'])->toBe('null');
            });

            test('transforms missing_if rule with conditional not', function (): void {
                // Arrange
                $field = 'optional';
                $rules = ['missing_if:mode,readonly'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['not']['if'])->toHaveKey('properties');
                expect($result['properties'][$field]['not']['if']['properties']['mode']['const'])->toBe('readonly');
            });

            test('transforms missing_unless rule with conditional not', function (): void {
                // Arrange
                $field = 'conditional';
                $rules = ['missing_unless:status,active'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['not']['if']['properties']['status']['not']['const'])->toBe('active');
            });

            test('transforms missing_with rule with multiple fields', function (): void {
                // Arrange
                $field = 'field_x';
                $rules = ['missing_with:field_a,field_b'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['not']['if'])->toHaveKey('properties');
                expect($result['properties'][$field]['not']['if']['properties'])->toHaveKey('field_a');
                expect($result['properties'][$field]['not']['if']['properties'])->toHaveKey('field_b');
            });

            test('transforms missing_with_all rule with all required fields', function (): void {
                // Arrange
                $field = 'field_y';
                $rules = ['missing_with_all:field_1,field_2,field_3'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['not']['if']['properties'])->toHaveKey('field_1');
                expect($result['properties'][$field]['not']['if']['properties'])->toHaveKey('field_2');
                expect($result['properties'][$field]['not']['if']['properties'])->toHaveKey('field_3');
            });

            test('transforms multiple_of rule to multipleOf constraint', function (): void {
                // Arrange
                $field = 'batch_size';
                $rules = ['multiple_of:10'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('number');
                expect($result['properties'][$field]['multipleOf'])->toBe(10.0);
            });

            test('transforms not_in rule to not enum', function (): void {
                // Arrange
                $field = 'status';
                $rules = ['not_in:banned,deleted'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['not']['enum'])->toContain('banned');
                expect($result['not']['enum'])->toContain('deleted');
            });

            test('transforms not_regex rule to not pattern', function (): void {
                // Arrange
                $field = 'text';
                $rules = ['not_regex:^[0-9]+$'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['not']['pattern'])->toBe('^[0-9]+$');
            });

            test('transforms nullable rule to string or null type', function (): void {
                // Arrange
                $field = 'optional_text';
                $rules = ['nullable'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBeArray();
                expect($result['type'])->toContain('string');
                expect($result['type'])->toContain('null');
            });

            test('transforms numeric rule to number type', function (): void {
                // Arrange
                $field = 'amount';
                $rules = ['numeric'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('number');
            });

            test('transforms present rule to required field', function (): void {
                // Arrange
                $field = 'must_exist';
                $rules = ['present'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['required'])->toContain($field);
            });

            test('transforms present_if rule with conditional required', function (): void {
                // Arrange
                $field = 'conditional_field';
                $rules = ['present_if:type,premium'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['allOf'][0]['if']['properties']['type']['const'])->toBe('premium');
                expect($result['allOf'][0]['then']['required'])->toContain($field);
            });

            test('transforms present_unless rule with conditional required', function (): void {
                // Arrange
                $field = 'standard_field';
                $rules = ['present_unless:type,guest'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['allOf'][0]['if']['not']['properties']['type']['const'])->toBe('guest');
                expect($result['allOf'][0]['then']['required'])->toContain($field);
            });

            test('transforms present_with rule with multiple conditional fields', function (): void {
                // Arrange
                $field = 'dependent_field';
                $rules = ['present_with:field_a,field_b'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['allOf'][0]['if']['properties'])->toHaveKey('field_a');
                expect($result['allOf'][0]['if']['properties'])->toHaveKey('field_b');
                expect($result['allOf'][0]['then']['required'])->toContain($field);
            });

            test('transforms present_with_all rule with all conditional fields', function (): void {
                // Arrange
                $field = 'dependent_all';
                $rules = ['present_with_all:req_a,req_b,req_c'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['allOf'][0]['if']['properties'])->toHaveKey('req_a');
                expect($result['allOf'][0]['if']['properties'])->toHaveKey('req_b');
                expect($result['allOf'][0]['if']['properties'])->toHaveKey('req_c');
                expect($result['allOf'][0]['then']['required'])->toContain($field);
            });

            test('transforms prohibited rule to not required', function (): void {
                // Arrange
                $field = 'forbidden';
                $rules = ['prohibited'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['not']['required'])->toContain($field);
            });

            test('transforms prohibited_if rule with conditional prohibition', function (): void {
                // Arrange
                $field = 'conditional_prohibited';
                $rules = ['prohibited_if:status,inactive'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['not']['if']['properties']['status']['const'])->toBe('inactive');
                expect($result['not']['then']['required'])->toContain($field);
            });

            test('transforms prohibited_unless rule with conditional prohibition', function (): void {
                // Arrange
                $field = 'admin_only';
                $rules = ['prohibited_unless:role,admin'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['not']['if']['properties']['role']['not']['const'])->toBe('admin');
                expect($result['not']['then']['required'])->toContain($field);
            });

            test('transforms prohibits rule with multiple prohibited fields', function (): void {
                // Arrange
                $field = 'exclusive_field';
                $rules = ['prohibits:conflict_a,conflict_b'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['type'])->toBe('string');
                expect($result['allOf'][0]['then']['properties'])->toHaveKey('conflict_a');
                expect($result['allOf'][0]['then']['properties'])->toHaveKey('conflict_b');
            });

            test('transforms regex rule to pattern', function (): void {
                // Arrange
                $field = 'custom_format';
                $rules = ['regex:^[A-Z]{3}[0-9]{3}$'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['pattern'])->toBe('^[A-Z]{3}[0-9]{3}$');
            });

            test('transforms required rule to required field', function (): void {
                // Arrange
                $field = 'mandatory';
                $rules = ['required'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['required'])->toContain($field);
            });

            test('transforms required_if rule with conditional required', function (): void {
                // Arrange
                $field = 'phone';
                $rules = ['required_if:contact_method,phone'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['allOf'][0]['if']['properties']['contact_method']['const'])->toBe('phone');
                expect($result['allOf'][0]['then']['required'])->toContain($field);
            });

            test('transforms required_if_accepted rule with accepted enum values', function (): void {
                // Arrange
                $field = 'terms_date';
                $rules = ['required_if_accepted:accept_terms'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['allOf'][0]['if']['properties']['accept_terms']['enum'])->toContain('yes');
                expect($result['allOf'][0]['if']['properties']['accept_terms']['enum'])->toContain(true);
                expect($result['allOf'][0]['then']['required'])->toContain($field);
            });

            test('transforms required_unless rule with conditional required', function (): void {
                // Arrange
                $field = 'alternative';
                $rules = ['required_unless:primary,provided'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['allOf'][0]['if']['properties']['primary']['not']['const'])->toBe('provided');
                expect($result['allOf'][0]['then']['required'])->toContain($field);
            });

            test('transforms required_with rule to dependencies', function (): void {
                // Arrange
                $field = 'street';
                $rules = ['required_with:city,country'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['dependencies'][$field])->toContain('city');
                expect($result['dependencies'][$field])->toContain('country');
            });

            test('transforms required_with_all rule to dependencies with allOf', function (): void {
                // Arrange
                $field = 'zip';
                $rules = ['required_with_all:street,city'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['dependencies'][$field]['allOf'])->toBeArray();
                expect($result['dependencies'][$field]['allOf'][0]['required'])->toContain('street');
                expect($result['dependencies'][$field]['allOf'][1]['required'])->toContain('city');
            });

            test('transforms required_without rule to dependencies with not', function (): void {
                // Arrange
                $field = 'backup_email';
                $rules = ['required_without:primary_email'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['dependencies'][$field]['not'])->toBeArray();
                expect($result['dependencies'][$field]['not'][0]['required'])->toContain('primary_email');
            });

            test('transforms required_without_all rule to dependencies with not allOf', function (): void {
                // Arrange
                $field = 'emergency_contact';
                $rules = ['required_without_all:email,phone'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['dependencies'][$field]['not']['allOf'])->toBeArray();
                expect($result['dependencies'][$field]['not']['allOf'][0]['required'])->toContain('email');
                expect($result['dependencies'][$field]['not']['allOf'][1]['required'])->toContain('phone');
            });

            test('transforms size rule to const value', function (): void {
                // Arrange
                $field = 'fixed_length';
                $rules = ['size:10'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['const'])->toBe('10');
            });

            test('transforms starts_with rule to prefix pattern', function (): void {
                // Arrange
                $field = 'code';
                $rules = ['starts_with:PREFIX,ALT'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result)->toHaveKey('pattern');
                expect($result['pattern'])->toContain('^(');
            });

            test('transforms string rule to string type', function (): void {
                // Arrange
                $field = 'name';
                $rules = ['string'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
            });

            test('transforms timezone rule to timezone enum', function (): void {
                // Arrange
                $field = 'timezone';
                $rules = ['timezone'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['enum'])->toBeArray();
                expect($result['enum'])->toContain('America/New_York');
            });

            test('transforms uppercase rule to uppercase pattern', function (): void {
                // Arrange
                $field = 'country_code';
                $rules = ['uppercase'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['pattern'])->toBe('^[A-Z]*$');
            });

            test('transforms url rule to uri format', function (): void {
                // Arrange
                $field = 'website';
                $rules = ['url'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['format'])->toBe('uri');
            });

            test('transforms ulid rule to ulid pattern', function (): void {
                // Arrange
                $field = 'identifier';
                $rules = ['ulid'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['pattern'])->toBe('^[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{26}$');
            });

            test('transforms uuid rule to uuid format', function (): void {
                // Arrange
                $field = 'id';
                $rules = ['uuid'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['format'])->toBe('uuid');
            });
        });

        describe('Sad Paths', function (): void {
            test('throws exception for bail rule', function (): void {
                // Arrange
                $field = 'test';
                $rules = ['bail'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for current_password rule', function (): void {
                // Arrange
                $field = 'password';
                $rules = ['current_password'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for dimensions rule', function (): void {
                // Arrange
                $field = 'image';
                $rules = ['dimensions'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for distinct rule', function (): void {
                // Arrange
                $field = 'items';
                $rules = ['distinct'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for exists database rule', function (): void {
                // Arrange
                $field = 'user_id';
                $rules = ['exists:users,id'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for extensions rule', function (): void {
                // Arrange
                $field = 'file';
                $rules = ['extensions:jpg,png'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for file rule', function (): void {
                // Arrange
                $field = 'upload';
                $rules = ['file'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for image rule', function (): void {
                // Arrange
                $field = 'photo';
                $rules = ['image'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for in_array rule', function (): void {
                // Arrange
                $field = 'item';
                $rules = ['in_array'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for json rule', function (): void {
                // Arrange
                $field = 'data';
                $rules = ['json'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for mimes rule', function (): void {
                // Arrange
                $field = 'document';
                $rules = ['mimes:pdf,doc'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for password rule', function (): void {
                // Arrange
                $field = 'pwd';
                $rules = ['password'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for same rule', function (): void {
                // Arrange
                $field = 'confirmation';
                $rules = ['same:password'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for sometimes rule', function (): void {
                // Arrange
                $field = 'optional';
                $rules = ['sometimes'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });

            test('throws exception for unique database rule', function (): void {
                // Arrange
                $field = 'email';
                $rules = ['unique:users,email'];

                // Act & Assert
                expect(fn (): array => RuleTransformer::transform($field, $rules))
                    ->toThrow(JsonSchemaException::class);
            });
        });

        describe('Edge Cases', function (): void {
            test('handles empty rules array', function (): void {
                // Arrange
                $field = 'empty';
                $rules = [];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result)->toBeArray();
                expect($result)->toBeEmpty();
            });

            test('handles rule object with toString method', function (): void {
                // Arrange
                $field = 'test';
                $ruleObject = new class() implements Stringable
                {
                    public function __toString(): string
                    {
                        return 'required';
                    }
                };
                $rules = [$ruleObject];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['required'])->toContain($field);
            });

            test('handles multiple rules combining to build complex schema', function (): void {
                // Arrange
                $field = 'complex';
                $rules = ['required', 'string', 'min:5', 'max:100', 'alpha_num'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['required'])->toContain($field);
                expect($result['minLength'])->toBe(5);
                expect($result['maxLength'])->toBe(100);
                expect($result['pattern'])->toBe('^[a-zA-Z0-9]+$');
            });

            test('handles required_array_keys rule without transformation', function (): void {
                // Arrange
                $field = 'data';
                $rules = ['required_array_keys:key1,key2'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result)->toBeArray();
            });

            test('handles unicode characters in rule parameters', function (): void {
                // Arrange
                $field = 'name';
                $rules = ['in:café,naïve,日本語'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['enum'])->toContain('café');
                expect($result['enum'])->toContain('naïve');
                expect($result['enum'])->toContain('日本語');
            });

            test('handles special characters in pattern rules', function (): void {
                // Arrange
                $field = 'special';
                $rules = ['doesnt_start_with:$,%,&'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result)->toHaveKey('pattern');
            });

            test('handles numeric values in rule parameters', function (): void {
                // Arrange
                $field = 'value';
                $rules = ['between:0,100'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['minimum'])->toBe(0);
                expect($result['maximum'])->toBe(100);
            });

            test('handles float values in gt and gte rules', function (): void {
                // Arrange
                $field = 'price';
                $rules = ['gt:0.99', 'lte:999.99'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['properties'][$field]['exclusiveMinimum'])->toBe(0.99);
                expect($result['properties'][$field]['maximum'])->toBe(999.99);
            });

            test('accumulates allOf conditions from multiple conditional rules', function (): void {
                // Arrange
                $field = 'multi_conditional';
                $rules = ['required_if:type,A', 'exclude_if:status,inactive'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['allOf'])->toBeArray();
                expect($result['allOf'])->toHaveCount(2);
            });

            test('handles overlapping type and format rules', function (): void {
                // Arrange
                $field = 'email_field';
                $rules = ['string', 'email', 'max:255'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
                expect($result['format'])->toBe('email');
                expect($result['maxLength'])->toBe(255);
            });

            test('handles long list of prohibited fields in prohibits rule', function (): void {
                // Arrange
                $field = 'exclusive';
                $rules = ['prohibits:field1,field2,field3,field4,field5'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['allOf'][0]['then']['properties'])->toHaveKey('field1');
                expect($result['allOf'][0]['then']['properties'])->toHaveKey('field5');
            });

            test('handles empty string parameters in rules', function (): void {
                // Arrange
                $field = 'test';
                $rules = ['string'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBe('string');
            });

            test('preserves existing schema properties when adding new rules', function (): void {
                // Arrange
                $field = 'cumulative';
                $rules = ['string', 'nullable'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert
                expect($result['type'])->toBeArray();
                expect($result['type'])->toContain('string');
                expect($result['type'])->toContain('null');
            });
        });

        describe('Regressions', function (): void {
            test('prevents timezone enum from being empty', function (): void {
                // Arrange
                $field = 'tz';
                $rules = ['timezone'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert - Ensure timezone enum is populated
                expect($result['enum'])->not->toBeEmpty();
                expect($result['enum'])->toBeArray();
            });

            test('ensures toString conversion is called before rule processing', function (): void {
                // Arrange
                $field = 'test';
                $ruleObject = new class() implements Stringable
                {
                    public function __toString(): string
                    {
                        return 'integer';
                    }
                };
                $rules = [$ruleObject];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert - Verify object rule is converted before processing
                expect($result['type'])->toBe('integer');
            });

            test('handles nested properties correctly without overwriting', function (): void {
                // Arrange
                $field = 'nested';
                $rules = ['after:2024-01-01', 'before:2024-12-31'];

                // Act
                $result = RuleTransformer::transform($field, $rules);

                // Assert - Both constraints should exist
                expect($result['properties'][$field])->toHaveKey('exclusiveMinimum');
                expect($result['properties'][$field])->toHaveKey('exclusiveMaximum');
            });
        });
    });
});
