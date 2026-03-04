<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Discovery\ComponentsData;
use Cline\Forrst\Discovery\DiscoveryData;
use Cline\Forrst\Discovery\DiscoveryServerData;
use Cline\Forrst\Discovery\ExternalDocsData;
use Cline\Forrst\Discovery\FunctionDescriptorData;
use Cline\Forrst\Discovery\InfoData;
use Cline\Forrst\Discovery\Resource\ResourceData;

describe('DiscoveryData', function (): void {
    describe('Happy Paths', function (): void {
        test('creates instance with required fields only', function (): void {
            // Arrange
            $info = new InfoData(
                title: 'Orders API',
                version: '2.3.0',
            );
            $functions = [
                new FunctionDescriptorData(
                    name: 'urn:cline:forrst:fn:orders:get',
                    version: '2.0.0',
                    arguments: [],
                ),
            ];

            // Act
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: $info,
                functions: $functions,
            );

            // Assert
            expect($discovery->forrst)->toBe('0.1.0')
                ->and($discovery->discovery)->toBe('0.1.0')
                ->and($discovery->info)->toBeInstanceOf(InfoData::class)
                ->and($discovery->functions)->toHaveCount(1)
                ->and($discovery->functions[0])->toBeInstanceOf(FunctionDescriptorData::class)
                ->and($discovery->servers)->toBeNull()
                ->and($discovery->resources)->toBeNull()
                ->and($discovery->components)->toBeNull()
                ->and($discovery->externalDocs)->toBeNull();
        });

        test('creates instance with all fields populated', function (): void {
            // Arrange
            $info = new InfoData(
                title: 'Orders API',
                version: '2.3.0',
                description: 'Order management service',
            );
            $functions = [
                new FunctionDescriptorData(
                    name: 'urn:cline:forrst:fn:orders:get',
                    version: '2.0.0',
                    arguments: [],
                ),
            ];
            $servers = [
                new DiscoveryServerData(
                    name: 'production',
                    url: 'https://api.example.com',
                ),
            ];
            $resources = [
                'order' => new ResourceData(
                    type: 'order',
                    attributes: [],
                ),
            ];
            $components = new ComponentsData(
                schemas: ['Money' => ['type' => 'object']],
            );
            $externalDocs = new ExternalDocsData(
                url: 'https://docs.example.com',
            );

            // Act
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: $info,
                functions: $functions,
                servers: $servers,
                resources: $resources,
                components: $components,
                externalDocs: $externalDocs,
            );

            // Assert
            expect($discovery->forrst)->toBe('0.1.0')
                ->and($discovery->discovery)->toBe('0.1.0')
                ->and($discovery->info)->toBeInstanceOf(InfoData::class)
                ->and($discovery->functions)->toHaveCount(1)
                ->and($discovery->servers)->toHaveCount(1)
                ->and($discovery->servers[0])->toBeInstanceOf(DiscoveryServerData::class)
                ->and($discovery->resources)->toHaveKey('order')
                ->and($discovery->resources['order'])->toBeInstanceOf(ResourceData::class)
                ->and($discovery->components)->toBeInstanceOf(ComponentsData::class)
                ->and($discovery->externalDocs)->toBeInstanceOf(ExternalDocsData::class);
        });

        test('creates instance with multiple functions', function (): void {
            // Arrange
            $info = new InfoData(title: 'API', version: '1.0.0');
            $functions = [
                new FunctionDescriptorData(name: 'urn:cline:forrst:fn:orders:get', version: '2.0.0', arguments: []),
                new FunctionDescriptorData(name: 'urn:cline:forrst:fn:orders:list', version: '2.0.0', arguments: []),
                new FunctionDescriptorData(name: 'urn:cline:forrst:fn:orders:create', version: '2.0.0', arguments: []),
            ];

            // Act
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: $info,
                functions: $functions,
            );

            // Assert
            expect($discovery->functions)->toHaveCount(3)
                ->and($discovery->functions[0]->name)->toBe('urn:cline:forrst:fn:orders:get')
                ->and($discovery->functions[1]->name)->toBe('urn:cline:forrst:fn:orders:list')
                ->and($discovery->functions[2]->name)->toBe('urn:cline:forrst:fn:orders:create');
        });

        test('creates instance with multiple servers', function (): void {
            // Arrange
            $info = new InfoData(title: 'API', version: '1.0.0');
            $servers = [
                new DiscoveryServerData(name: 'production', url: 'https://api.prod.com'),
                new DiscoveryServerData(name: 'staging', url: 'https://api.staging.com'),
            ];

            // Act
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: $info,
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
                servers: $servers,
            );

            // Assert
            expect($discovery->servers)->toHaveCount(2)
                ->and($discovery->servers[0]->name)->toBe('production')
                ->and($discovery->servers[1]->name)->toBe('staging');
        });

        test('creates instance with multiple resources', function (): void {
            // Arrange
            $info = new InfoData(title: 'API', version: '1.0.0');
            $resources = [
                'order' => new ResourceData(type: 'order', attributes: []),
                'customer' => new ResourceData(type: 'customer', attributes: []),
            ];

            // Act
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: $info,
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
                resources: $resources,
            );

            // Assert
            expect($discovery->resources)->toHaveKey('order')
                ->and($discovery->resources)->toHaveKey('customer')
                ->and($discovery->resources['order']->type)->toBe('order')
                ->and($discovery->resources['customer']->type)->toBe('customer');
        });

        test('toArray maps external_docs correctly', function (): void {
            // Arrange
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
                externalDocs: new ExternalDocsData(
                    url: 'https://docs.example.com',
                    description: 'Full documentation',
                ),
            );

            // Act
            $array = $discovery->toArray();

            // Assert
            expect($array)->toHaveKey('externalDocs')
                ->and($array['externalDocs'])->toBeArray()
                ->and($array['externalDocs']['url'])->toBe('https://docs.example.com')
                ->and($array)->not->toHaveKey('external_docs');
        });

        test('toArray includes all required fields', function (): void {
            // Arrange
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
            );

            // Act
            $array = $discovery->toArray();

            // Assert
            expect($array)->toHaveKey('forrst')
                ->and($array)->toHaveKey('discovery')
                ->and($array)->toHaveKey('info')
                ->and($array)->toHaveKey('functions')
                ->and($array['forrst'])->toBe('0.1.0')
                ->and($array['discovery'])->toBe('0.1.0')
                ->and($array['info'])->toBeArray()
                ->and($array['functions'])->toHaveCount(1);
        });

        test('toArray handles null optional fields', function (): void {
            // Arrange
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
            );

            // Act
            $array = $discovery->toArray();

            // Assert - Spatie Data may include null fields, verify they are null
            if (array_key_exists('servers', $array)) {
                expect($array['servers'])->toBeNull();
            }

            expect($array)->toHaveKey('forrst')
                ->and($array)->toHaveKey('discovery')
                ->and($array)->toHaveKey('info')
                ->and($array)->toHaveKey('functions');
        });

        test('toArray with nested Data objects converts correctly', function (): void {
            // Arrange
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(
                    title: 'Orders API',
                    version: '2.3.0',
                    description: 'Order management',
                ),
                functions: [
                    new FunctionDescriptorData(
                        name: 'urn:cline:forrst:fn:orders:get',
                        version: '2.0.0',
                        arguments: [],
                    ),
                ],
            );

            // Act
            $array = $discovery->toArray();

            // Assert
            expect($array['info'])->toBeArray()
                ->and($array['info']['title'])->toBe('Orders API')
                ->and($array['functions'])->toBeArray()
                ->and($array['functions'][0])->toBeArray()
                ->and($array['functions'][0]['name'])->toBe('urn:cline:forrst:fn:orders:get');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles null servers', function (): void {
            // Arrange & Act
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
            );

            // Assert
            expect($discovery->servers)->toBeNull();
        });

        test('handles null resources', function (): void {
            // Arrange & Act
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
                resources: null,
            );

            // Assert
            expect($discovery->resources)->toBeNull();
        });

        test('handles null components', function (): void {
            // Arrange & Act
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
                components: null,
            );

            // Assert
            expect($discovery->components)->toBeNull();
        });

        test('handles different version formats', function (): void {
            // Arrange & Act
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '2.3.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
            );

            // Assert
            expect($discovery->forrst)->toBe('0.1.0')
                ->and($discovery->discovery)->toBe('0.1.0');
        });

        test('resources map preserves keys', function (): void {
            // Arrange
            $resources = [
                'order' => new ResourceData(type: 'order', attributes: []),
                'custom_key' => new ResourceData(type: 'payment', attributes: []),
            ];

            // Act
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
                resources: $resources,
            );

            // Assert
            expect($discovery->resources)->toHaveKey('order')
                ->and($discovery->resources)->toHaveKey('custom_key')
                ->and($discovery->resources['custom_key']->type)->toBe('payment');
        });
    });

    describe('Sad Paths', function (): void {
        test('validates required forrst field exists', function (): void {
            // Arrange
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
            );

            // Act & Assert
            expect($discovery->forrst)->toBe('0.1.0');
        });

        test('validates required discovery field exists', function (): void {
            // Arrange
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
            );

            // Act & Assert
            expect($discovery->discovery)->toBe('0.1.0');
        });

        test('validates required info field exists', function (): void {
            // Arrange
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
            );

            // Act & Assert
            expect($discovery->info)->toBeInstanceOf(InfoData::class);
        });

        test('validates required functions field exists', function (): void {
            // Arrange
            $discovery = new DiscoveryData(
                forrst: '0.1.0',
                discovery: '0.1.0',
                info: new InfoData(title: 'API', version: '1.0.0'),
                functions: [new FunctionDescriptorData(name: 'urn:cline:forrst:fn:test', version: '1.0.0', arguments: [])],
            );

            // Act & Assert
            expect($discovery->functions)->toBeArray();
        });
    });
});
