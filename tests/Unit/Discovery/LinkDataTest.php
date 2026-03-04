<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Discovery\DiscoveryServerData;
use Cline\Forrst\Discovery\LinkData;
use Cline\Forrst\Discovery\ServerVariableData;

describe('LinkData', function (): void {
    describe('Happy Paths', function (): void {
        test('creates instance with required fields only', function (): void {
            // Arrange & Act
            $link = new LinkData(
                name: 'GetEventVenue',
            );

            // Assert
            expect($link->name)->toBe('GetEventVenue')
                ->and($link->summary)->toBeNull()
                ->and($link->description)->toBeNull()
                ->and($link->function)->toBeNull()
                ->and($link->params)->toBeNull()
                ->and($link->server)->toBeNull();
        });

        test('creates instance with all fields populated', function (): void {
            // Arrange & Act
            $link = new LinkData(
                name: 'GetEventVenue',
                summary: 'Retrieve the venue for this event',
                description: 'Navigates to the venue details using the venue_id from the event.',
                function: 'urn:cline:forrst:fn:venues:get',
                params: ['venue_id' => '$result.venue.id'],
                server: new DiscoveryServerData(
                    name: 'venues',
                    url: 'https://venues.example.com/api',
                ),
            );

            // Assert
            expect($link->name)->toBe('GetEventVenue')
                ->and($link->summary)->toBe('Retrieve the venue for this event')
                ->and($link->description)->toBe('Navigates to the venue details using the venue_id from the event.')
                ->and($link->function)->toBe('urn:cline:forrst:fn:venues:get')
                ->and($link->params)->toBe(['venue_id' => '$result.venue.id'])
                ->and($link->server)->toBeInstanceOf(DiscoveryServerData::class);
        });

        test('creates instance with function reference', function (): void {
            // Arrange & Act
            $link = new LinkData(
                name: 'ListOrderItems',
                function: 'urn:cline:forrst:fn:order_items:list',
                params: ['order_id' => '$result.id'],
            );

            // Assert
            expect($link->function)->toBe('urn:cline:forrst:fn:order_items:list')
                ->and($link->params)->toHaveKey('order_id');
        });

        test('creates instance with runtime expression params', function (): void {
            // Arrange & Act
            $link = new LinkData(
                name: 'GetCustomerDetails',
                function: 'urn:cline:forrst:fn:customers:get',
                params: [
                    'customer_id' => '$result.customer.id',
                    'include_orders' => true,
                ],
            );

            // Assert
            expect($link->params['customer_id'])->toBe('$result.customer.id')
                ->and($link->params['include_orders'])->toBeTrue();
        });

        test('toArray includes all required fields', function (): void {
            // Arrange
            $link = new LinkData(
                name: 'TestLink',
            );

            // Act
            $array = $link->toArray();

            // Assert
            expect($array)->toHaveKey('name')
                ->and($array['name'])->toBe('TestLink');
        });

        test('toArray includes nested server object', function (): void {
            // Arrange
            $link = new LinkData(
                name: 'CrossServiceLink',
                function: 'urn:cline:forrst:fn:external:function',
                server: new DiscoveryServerData(
                    name: 'external',
                    url: 'https://external.example.com/api',
                    summary: 'External service endpoint',
                ),
            );

            // Act
            $array = $link->toArray();

            // Assert
            expect($array['server'])->toBeArray()
                ->and($array['server']['name'])->toBe('external')
                ->and($array['server']['url'])->toBe('https://external.example.com/api');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles informational link without function', function (): void {
            // Arrange & Act
            $link = new LinkData(
                name: 'DocumentationLink',
                summary: 'View full documentation',
                description: 'This link provides context but does not invoke a function.',
            );

            // Assert
            expect($link->function)->toBeNull()
                ->and($link->params)->toBeNull()
                ->and($link->summary)->toBe('View full documentation');
        });

        test('handles empty params array', function (): void {
            // Arrange & Act
            $link = new LinkData(
                name: 'NoParams',
                function: 'urn:cline:forrst:fn:stateless:function',
                params: [],
            );

            // Assert
            expect($link->params)->toBe([])
                ->and($link->params)->toHaveCount(0);
        });

        test('handles params with complex runtime expressions', function (): void {
            // Arrange & Act
            $link = new LinkData(
                name: 'ComplexLink',
                function: 'urn:cline:forrst:fn:nested:function',
                params: [
                    'id' => '$result.data.items[0].id',
                    'timestamp' => '$result.metadata.created_at',
                ],
            );

            // Assert
            expect($link->params['id'])->toBe('$result.data.items[0].id')
                ->and($link->params['timestamp'])->toBe('$result.metadata.created_at');
        });

        test('handles params with static and dynamic values mixed', function (): void {
            // Arrange & Act
            $link = new LinkData(
                name: 'MixedParams',
                function: 'urn:cline:forrst:fn:mixed:function',
                params: [
                    'dynamic_id' => '$result.id',
                    'static_type' => 'default',
                    'static_limit' => 10,
                    'static_flag' => false,
                ],
            );

            // Assert
            expect($link->params)->toHaveCount(4)
                ->and($link->params['dynamic_id'])->toBe('$result.id')
                ->and($link->params['static_type'])->toBe('default')
                ->and($link->params['static_limit'])->toBe(10)
                ->and($link->params['static_flag'])->toBeFalse();
        });

        test('handles server with full configuration', function (): void {
            // Arrange & Act
            $link = new LinkData(
                name: 'FullServerLink',
                function: 'urn:cline:forrst:fn:remote:function',
                server: new DiscoveryServerData(
                    name: 'production',
                    url: 'https://api.example.com/v2',
                    summary: 'Production API server',
                    description: 'Main production endpoint for the API.',
                    variables: [
                        'version' => new ServerVariableData(
                            default: 'v2',
                            enum: ['v1', 'v2'],
                        ),
                    ],
                ),
            );

            // Assert
            expect($link->server->name)->toBe('production')
                ->and($link->server->url)->toBe('https://api.example.com/v2')
                ->and($link->server->summary)->toBe('Production API server')
                ->and($link->server->variables)->toHaveKey('version');
        });
    });
});
