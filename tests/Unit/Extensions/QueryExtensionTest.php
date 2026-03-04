<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ProtocolData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Extensions\ExtensionUrn;
use Cline\Forrst\Extensions\QueryExtension;

describe('QueryExtension', function (): void {
    describe('Happy Paths', function (): void {
        test('getUrn returns correct URN constant', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $urn = $extension->getUrn();

            // Assert
            expect($urn)->toBe(ExtensionUrn::Query->value)
                ->and($urn)->toBe('urn:cline:forrst:ext:query');
        });

        test('isErrorFatal returns true for query validation errors', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $result = $extension->isErrorFatal();

            // Assert
            expect($result)->toBeTrue();
        });

        test('getFilters extracts filters from options', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $options = [
                'filters' => [
                    'self' => [
                        ['attribute' => 'status', 'operator' => 'equals', 'value' => 'active'],
                    ],
                ],
            ];

            // Act
            $filters = $extension->getFilters($options);

            // Assert
            expect($filters)->toHaveKey('self')
                ->and($filters['self'])->toHaveCount(1)
                ->and($filters['self'][0]['attribute'])->toBe('status');
        });

        test('getSorts extracts sorts from options', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $options = [
                'sorts' => [
                    ['attribute' => 'created_at', 'direction' => 'desc'],
                ],
            ];

            // Act
            $sorts = $extension->getSorts($options);

            // Assert
            expect($sorts)->toHaveCount(1)
                ->and($sorts[0]['attribute'])->toBe('created_at')
                ->and($sorts[0]['direction'])->toBe('desc');
        });

        test('getPagination extracts pagination from options', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $options = [
                'pagination' => [
                    'limit' => 25,
                    'cursor' => 'abc123',
                ],
            ];

            // Act
            $pagination = $extension->getPagination($options);

            // Assert
            expect($pagination)->toHaveKey('limit', 25)
                ->and($pagination)->toHaveKey('cursor', 'abc123');
        });

        test('getFields extracts sparse fieldsets from options', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $options = [
                'fields' => [
                    'self' => ['id', 'status', 'total_amount'],
                    'customer' => ['id', 'name'],
                ],
            ];

            // Act
            $fields = $extension->getFields($options);

            // Assert
            expect($fields)->toHaveKey('self')
                ->and($fields)->toHaveKey('customer')
                ->and($fields['self'])->toBe(['id', 'status', 'total_amount']);
        });

        test('getRelationships extracts relationships from options', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $options = [
                'relationships' => ['customer', 'items'],
            ];

            // Act
            $relationships = $extension->getRelationships($options);

            // Assert
            expect($relationships)->toBe(['customer', 'items']);
        });

        test('getUsedCapabilities detects filtering capability', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $options = [
                'filters' => [
                    'self' => [['attribute' => 'status', 'operator' => 'equals', 'value' => 'active']],
                ],
            ];

            // Act
            $capabilities = $extension->getUsedCapabilities($options);

            // Assert
            expect($capabilities)->toContain(QueryExtension::CAPABILITY_FILTERING);
        });

        test('getUsedCapabilities detects all capabilities', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $options = [
                'filters' => ['self' => [['attribute' => 'status', 'operator' => 'equals', 'value' => 'active']]],
                'sorts' => [['attribute' => 'created_at', 'direction' => 'desc']],
                'pagination' => ['limit' => 25],
                'fields' => ['self' => ['id', 'status']],
                'relationships' => ['customer'],
            ];

            // Act
            $capabilities = $extension->getUsedCapabilities($options);

            // Assert
            expect($capabilities)->toContain(QueryExtension::CAPABILITY_FILTERING)
                ->and($capabilities)->toContain(QueryExtension::CAPABILITY_SORTING)
                ->and($capabilities)->toContain(QueryExtension::CAPABILITY_PAGINATION)
                ->and($capabilities)->toContain(QueryExtension::CAPABILITY_FIELDS)
                ->and($capabilities)->toContain(QueryExtension::CAPABILITY_RELATIONSHIPS);
        });

        test('buildOffsetPagination creates offset pagination metadata', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $pagination = $extension->buildOffsetPagination(25, 0, 150, true);

            // Assert
            expect($pagination)->toHaveKey('limit', 25)
                ->and($pagination)->toHaveKey('offset', 0)
                ->and($pagination)->toHaveKey('total', 150)
                ->and($pagination)->toHaveKey('has_more', true);
        });

        test('buildCursorPagination creates cursor pagination metadata', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $pagination = $extension->buildCursorPagination(25, 'next_abc', 'prev_xyz', true);

            // Assert
            expect($pagination)->toHaveKey('limit', 25)
                ->and($pagination)->toHaveKey('next_cursor', 'next_abc')
                ->and($pagination)->toHaveKey('prev_cursor', 'prev_xyz')
                ->and($pagination)->toHaveKey('has_more', true);
        });

        test('buildKeysetPagination creates keyset pagination metadata', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $pagination = $extension->buildKeysetPagination(100, '12350', '12300', true);

            // Assert
            expect($pagination)->toHaveKey('limit', 100)
                ->and($pagination)->toHaveKey('newest_id', '12350')
                ->and($pagination)->toHaveKey('oldest_id', '12300')
                ->and($pagination)->toHaveKey('has_more', true);
        });

        test('buildSort creates sort directive', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $sort = $extension->buildSort('created_at', QueryExtension::SORT_DESC);

            // Assert
            expect($sort)->toBe([
                'attribute' => 'created_at',
                'direction' => 'desc',
            ]);
        });

        test('buildFilter creates filter condition', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $filter = $extension->buildFilter('status', 'equals', 'active');

            // Assert
            expect($filter)->toHaveKey('attribute', 'status')
                ->and($filter)->toHaveKey('operator', 'equals')
                ->and($filter)->toHaveKey('value', 'active')
                ->and($filter)->not->toHaveKey('boolean');
        });

        test('buildFilter includes boolean when not AND', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $filter = $extension->buildFilter('type', 'equals', 'premium', QueryExtension::BOOLEAN_OR);

            // Assert
            expect($filter)->toHaveKey('boolean', 'or');
        });

        test('enrichResponse adds query extension to response', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $response = ResponseData::success(['data' => []], '01JFEX0001');
            $capabilities = [QueryExtension::CAPABILITY_FILTERING, QueryExtension::CAPABILITY_PAGINATION];

            // Act
            $enriched = $extension->enrichResponse($response, $capabilities);

            // Assert
            expect($enriched)->toBeInstanceOf(ResponseData::class)
                ->and($enriched->extensions)->toHaveCount(1);

            $ext = $enriched->extensions[0];
            expect($ext->urn)->toBe(ExtensionUrn::Query->value)
                ->and($ext->data)->toHaveKey('capabilities', $capabilities);
        });

        test('isOffsetPagination detects offset pagination', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act & Assert
            expect($extension->isOffsetPagination(['limit' => 25, 'offset' => 0]))->toBeTrue();
            expect($extension->isOffsetPagination(['limit' => 25]))->toBeTrue();
            expect($extension->isOffsetPagination(['limit' => 25, 'cursor' => 'abc']))->toBeFalse();
        });

        test('isCursorPagination detects cursor pagination', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act & Assert
            expect($extension->isCursorPagination(['limit' => 25, 'cursor' => 'abc']))->toBeTrue();
            expect($extension->isCursorPagination(['limit' => 25]))->toBeFalse();
        });

        test('isKeysetPagination detects keyset pagination', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act & Assert
            expect($extension->isKeysetPagination(['limit' => 25, 'after_id' => '123']))->toBeTrue();
            expect($extension->isKeysetPagination(['limit' => 25, 'before_id' => '123']))->toBeTrue();
            expect($extension->isKeysetPagination(['limit' => 25]))->toBeFalse();
        });

        test('getPaginationLimit returns limit with default', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act & Assert
            expect($extension->getPaginationLimit(['limit' => 50]))->toBe(50);
            expect($extension->getPaginationLimit([]))->toBe(25);
            expect($extension->getPaginationLimit(['limit' => 200]))->toBe(100);
        });

        test('getPaginationLimit respects custom max', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $limit = $extension->getPaginationLimit(['limit' => 500], 25, 250);

            // Assert
            expect($limit)->toBe(250);
        });
    });

    describe('Edge Cases', function (): void {
        test('getFilters returns empty array for null options', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $filters = $extension->getFilters(null);

            // Assert
            expect($filters)->toBe([]);
        });

        test('getSorts returns empty array for empty options', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $sorts = $extension->getSorts([]);

            // Assert
            expect($sorts)->toBe([]);
        });

        test('getPagination returns empty array when not specified', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $pagination = $extension->getPagination(['filters' => []]);

            // Assert
            expect($pagination)->toBe([]);
        });

        test('getUsedCapabilities returns empty array for null options', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $capabilities = $extension->getUsedCapabilities(null);

            // Assert
            expect($capabilities)->toBe([]);
        });

        test('getUsedCapabilities returns empty array for empty options', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $capabilities = $extension->getUsedCapabilities([]);

            // Assert
            expect($capabilities)->toBe([]);
        });

        test('buildOffsetPagination omits total when null', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $pagination = $extension->buildOffsetPagination(25, 0);

            // Assert
            expect($pagination)->not->toHaveKey('total');
        });

        test('buildCursorPagination omits cursors when null', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $pagination = $extension->buildCursorPagination(25);

            // Assert
            expect($pagination)->not->toHaveKey('next_cursor')
                ->and($pagination)->not->toHaveKey('prev_cursor');
        });

        test('buildKeysetPagination omits IDs when null', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $pagination = $extension->buildKeysetPagination(100);

            // Assert
            expect($pagination)->not->toHaveKey('newest_id')
                ->and($pagination)->not->toHaveKey('oldest_id');
        });

        test('buildSort uses ascending direction by default', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $sort = $extension->buildSort('name');

            // Assert
            expect($sort['direction'])->toBe(QueryExtension::SORT_ASC);
        });

        test('buildFilter handles null value for null operators', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $filter = $extension->buildFilter('deleted_at', 'is_null');

            // Assert
            expect($filter)->toHaveKey('attribute', 'deleted_at')
                ->and($filter)->toHaveKey('operator', 'is_null')
                ->and($filter)->not->toHaveKey('value');
        });

        test('enrichResponse appends to existing extensions', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $existingExt = ExtensionData::response('urn:cline:forrst:ext:other', ['data' => 'test']);
            $response = new ResponseData(
                protocol: ProtocolData::forrst(),
                id: '01JFEX0002',
                result: ['ok' => true],
                extensions: [$existingExt],
            );

            // Act
            $enriched = $extension->enrichResponse($response, []);

            // Assert
            expect($enriched->extensions)->toHaveCount(2);
        });

        test('enrichResponse with empty capabilities', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $response = ResponseData::success(['data' => []], '01JFEX0003');

            // Act
            $enriched = $extension->enrichResponse($response, []);

            // Assert
            $ext = $enriched->extensions[0];
            expect($ext->data['capabilities'])->toBe([]);
        });

        test('getPaginationLimit with custom default', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $limit = $extension->getPaginationLimit([], 50);

            // Assert
            expect($limit)->toBe(50);
        });

        test('getFilters with complex nested filters', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $options = [
                'filters' => [
                    'self' => [
                        ['attribute' => 'status', 'operator' => 'equals', 'value' => 'active'],
                        ['attribute' => 'type', 'operator' => 'in', 'value' => ['a', 'b', 'c']],
                    ],
                    'customer' => [
                        ['attribute' => 'verified', 'operator' => 'equals', 'value' => true],
                    ],
                ],
            ];

            // Act
            $filters = $extension->getFilters($options);

            // Assert
            expect($filters)->toHaveKey('self')
                ->and($filters)->toHaveKey('customer')
                ->and($filters['self'])->toHaveCount(2)
                ->and($filters['customer'])->toHaveCount(1);
        });
    });

    describe('Sad Paths', function (): void {
        test('getUsedCapabilities handles partial options', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $options = [
                'filters' => [], // Empty - should not count
                'sorts' => [['attribute' => 'created_at', 'direction' => 'desc']], // Has content
            ];

            // Act
            $capabilities = $extension->getUsedCapabilities($options);

            // Assert
            expect($capabilities)->not->toContain(QueryExtension::CAPABILITY_FILTERING)
                ->and($capabilities)->toContain(QueryExtension::CAPABILITY_SORTING);
        });

        test('enrichResponse preserves response data', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $response = ResponseData::success([
                'data' => [
                    ['type' => 'order', 'id' => '123', 'attributes' => ['status' => 'pending']],
                ],
                'meta' => ['pagination' => ['limit' => 25, 'has_more' => true]],
            ], '01JFEX0004');

            // Act
            $enriched = $extension->enrichResponse($response, [QueryExtension::CAPABILITY_PAGINATION]);

            // Assert
            expect($enriched->result)->toBe($response->result)
                ->and($enriched->id)->toBe($response->id);
        });

        test('buildFilter with OR boolean for multiple conditions', function (): void {
            // Arrange
            $extension = new QueryExtension();

            // Act
            $filter1 = $extension->buildFilter('status', 'equals', 'pending');
            $filter2 = $extension->buildFilter('status', 'equals', 'processing', QueryExtension::BOOLEAN_OR);

            // Assert
            expect($filter1)->not->toHaveKey('boolean')
                ->and($filter2)->toHaveKey('boolean', 'or');
        });

        test('pagination style detection with mixed parameters', function (): void {
            // Arrange
            $extension = new QueryExtension();
            $pagination = ['limit' => 25, 'offset' => 50, 'after_id' => '123'];

            // Act & Assert - keyset takes precedence when after_id is present
            expect($extension->isKeysetPagination($pagination))->toBeTrue();
        });
    });
});
