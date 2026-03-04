<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Contracts\ExtensionInterface;
use Cline\Forrst\Extensions\ExtensionRegistry;
use Mockery as m;

describe('ExtensionRegistry', function (): void {
    beforeEach(function (): void {
        $this->registry = new ExtensionRegistry();
    });

    describe('Happy Paths', function (): void {
        test('registers extension by URN successfully', function (): void {
            // Arrange
            $extension = m::mock(ExtensionInterface::class);
            $extension->shouldReceive('getUrn')->andReturn('urn:test:extension');
            $extension->shouldReceive('getSubscribedEvents')->andReturn([]);

            // Act
            $this->registry->register($extension);

            // Assert
            expect($this->registry->has('urn:test:extension'))->toBeTrue();
        });

        test('retrieves registered extension by URN', function (): void {
            // Arrange
            $extension = m::mock(ExtensionInterface::class);
            $extension->shouldReceive('getUrn')->andReturn('urn:test:extension');
            $extension->shouldReceive('getSubscribedEvents')->andReturn([]);
            $this->registry->register($extension);

            // Act
            $result = $this->registry->get('urn:test:extension');

            // Assert
            expect($result)->toBe($extension);
        });

        test('returns true when checking for registered extension', function (): void {
            // Arrange
            $extension = m::mock(ExtensionInterface::class);
            $extension->shouldReceive('getUrn')->andReturn('urn:test:extension');
            $extension->shouldReceive('getSubscribedEvents')->andReturn([]);
            $this->registry->register($extension);

            // Act
            $result = $this->registry->has('urn:test:extension');

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns all registered extensions as associative array', function (): void {
            // Arrange
            $ext1 = m::mock(ExtensionInterface::class);
            $ext1->shouldReceive('getUrn')->andReturn('urn:test:ext1');
            $ext1->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext2 = m::mock(ExtensionInterface::class);
            $ext2->shouldReceive('getUrn')->andReturn('urn:test:ext2');
            $ext2->shouldReceive('getSubscribedEvents')->andReturn([]);

            $this->registry->register($ext1);
            $this->registry->register($ext2);

            // Act
            $result = $this->registry->all();

            // Assert
            expect($result)->toHaveCount(2)
                ->and($result)->toHaveKey('urn:test:ext1')
                ->and($result)->toHaveKey('urn:test:ext2')
                ->and($result['urn:test:ext1'])->toBe($ext1)
                ->and($result['urn:test:ext2'])->toBe($ext2);
        });

        test('returns list of all registered URNs', function (): void {
            // Arrange
            $ext1 = m::mock(ExtensionInterface::class);
            $ext1->shouldReceive('getUrn')->andReturn('urn:test:ext1');
            $ext1->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext2 = m::mock(ExtensionInterface::class);
            $ext2->shouldReceive('getUrn')->andReturn('urn:test:ext2');
            $ext2->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext3 = m::mock(ExtensionInterface::class);
            $ext3->shouldReceive('getUrn')->andReturn('urn:test:ext3');
            $ext3->shouldReceive('getSubscribedEvents')->andReturn([]);

            $this->registry->register($ext1);
            $this->registry->register($ext2);
            $this->registry->register($ext3);

            // Act
            $result = $this->registry->getUrns();

            // Assert
            expect($result)->toHaveCount(3)
                ->and($result)->toContain('urn:test:ext1')
                ->and($result)->toContain('urn:test:ext2')
                ->and($result)->toContain('urn:test:ext3');
        });

        test('converts all extensions to capabilities format', function (): void {
            // Arrange
            $ext1 = m::mock(ExtensionInterface::class);
            $ext1->shouldReceive('getUrn')->andReturn('urn:test:ext1');
            $ext1->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext1->shouldReceive('toCapabilities')->andReturn([
                'urn' => 'urn:test:ext1',
                'documentation' => 'https://example.com/ext1',
            ]);

            $ext2 = m::mock(ExtensionInterface::class);
            $ext2->shouldReceive('getUrn')->andReturn('urn:test:ext2');
            $ext2->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext2->shouldReceive('toCapabilities')->andReturn([
                'urn' => 'urn:test:ext2',
            ]);

            $this->registry->register($ext1);
            $this->registry->register($ext2);

            // Act
            $result = $this->registry->toCapabilities();

            // Assert
            expect($result)->toHaveCount(2)
                ->and($result[0])->toMatchArray([
                    'urn' => 'urn:test:ext1',
                    'documentation' => 'https://example.com/ext1',
                ])
                ->and($result[1])->toMatchArray([
                    'urn' => 'urn:test:ext2',
                ]);
        });

        test('unregisters extension by URN successfully', function (): void {
            // Arrange
            $extension = m::mock(ExtensionInterface::class);
            $extension->shouldReceive('getUrn')->andReturn('urn:test:extension');
            $extension->shouldReceive('getSubscribedEvents')->andReturn([]);
            $this->registry->register($extension);

            // Act
            $this->registry->unregister('urn:test:extension');

            // Assert
            expect($this->registry->has('urn:test:extension'))->toBeFalse();
        });

        test('replaces existing extension when registering with same URN', function (): void {
            // Arrange
            $ext1 = m::mock(ExtensionInterface::class);
            $ext1->shouldReceive('getUrn')->andReturn('urn:test:extension');
            $ext1->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext2 = m::mock(ExtensionInterface::class);
            $ext2->shouldReceive('getUrn')->andReturn('urn:test:extension');
            $ext2->shouldReceive('getSubscribedEvents')->andReturn([]);

            $this->registry->register($ext1);

            // Act
            $this->registry->register($ext2);

            // Assert
            expect($this->registry->get('urn:test:extension'))->toBe($ext2)
                ->and($this->registry->all())->toHaveCount(1);
        });
    });

    describe('Sad Paths', function (): void {
        test('returns null when retrieving non-existent extension', function (): void {
            // Act
            $result = $this->registry->get('urn:nonexistent:extension');

            // Assert
            expect($result)->toBeNull();
        });

        test('returns false when checking for non-existent extension', function (): void {
            // Act
            $result = $this->registry->has('urn:nonexistent:extension');

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('returns empty array when no extensions are registered', function (): void {
            // Act
            $result = $this->registry->all();

            // Assert
            expect($result)->toBeEmpty();
        });

        test('returns empty array when getting URNs with no registered extensions', function (): void {
            // Act
            $result = $this->registry->getUrns();

            // Assert
            expect($result)->toBeEmpty();
        });

        test('returns empty array when converting to capabilities with no extensions', function (): void {
            // Act
            $result = $this->registry->toCapabilities();

            // Assert
            expect($result)->toBeEmpty();
        });

        test('handles unregistering non-existent extension without error', function (): void {
            // Act & Assert
            expect(fn () => $this->registry->unregister('urn:nonexistent:extension'))
                ->not->toThrow(Exception::class);
        });

        test('handles multiple unregister calls for same URN', function (): void {
            // Arrange
            $extension = m::mock(ExtensionInterface::class);
            $extension->shouldReceive('getUrn')->andReturn('urn:test:extension');
            $extension->shouldReceive('getSubscribedEvents')->andReturn([]);
            $this->registry->register($extension);

            // Act
            $this->registry->unregister('urn:test:extension');
            $this->registry->unregister('urn:test:extension');

            // Assert
            expect($this->registry->has('urn:test:extension'))->toBeFalse();
        });

        test('maintains correct count after register and unregister operations', function (): void {
            // Arrange
            $ext1 = m::mock(ExtensionInterface::class);
            $ext1->shouldReceive('getUrn')->andReturn('urn:test:ext1');
            $ext1->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext2 = m::mock(ExtensionInterface::class);
            $ext2->shouldReceive('getUrn')->andReturn('urn:test:ext2');
            $ext2->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext3 = m::mock(ExtensionInterface::class);
            $ext3->shouldReceive('getUrn')->andReturn('urn:test:ext3');
            $ext3->shouldReceive('getSubscribedEvents')->andReturn([]);

            // Act
            $this->registry->register($ext1);
            $this->registry->register($ext2);
            $this->registry->register($ext3);
            $this->registry->unregister('urn:test:ext2');

            // Assert
            expect($this->registry->all())->toHaveCount(2)
                ->and($this->registry->has('urn:test:ext1'))->toBeTrue()
                ->and($this->registry->has('urn:test:ext2'))->toBeFalse()
                ->and($this->registry->has('urn:test:ext3'))->toBeTrue();
        });

        test('handles URN with special characters correctly', function (): void {
            // Arrange
            $extension = m::mock(ExtensionInterface::class);
            $extension->shouldReceive('getUrn')->andReturn('urn:test:ext:special-chars_123');
            $extension->shouldReceive('getSubscribedEvents')->andReturn([]);

            // Act
            $this->registry->register($extension);

            // Assert
            expect($this->registry->has('urn:test:ext:special-chars_123'))->toBeTrue()
                ->and($this->registry->get('urn:test:ext:special-chars_123'))->toBe($extension);
        });

        test('toCapabilities maintains order of registration', function (): void {
            // Arrange
            $ext1 = m::mock(ExtensionInterface::class);
            $ext1->shouldReceive('getUrn')->andReturn('urn:test:ext1');
            $ext1->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext1->shouldReceive('toCapabilities')->andReturn(['urn' => 'urn:test:ext1']);

            $ext2 = m::mock(ExtensionInterface::class);
            $ext2->shouldReceive('getUrn')->andReturn('urn:test:ext2');
            $ext2->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext2->shouldReceive('toCapabilities')->andReturn(['urn' => 'urn:test:ext2']);

            $ext3 = m::mock(ExtensionInterface::class);
            $ext3->shouldReceive('getUrn')->andReturn('urn:test:ext3');
            $ext3->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext3->shouldReceive('toCapabilities')->andReturn(['urn' => 'urn:test:ext3']);

            $this->registry->register($ext1);
            $this->registry->register($ext2);
            $this->registry->register($ext3);

            // Act
            $result = $this->registry->toCapabilities();

            // Assert
            expect($result)->toHaveCount(3)
                ->and($result[0]['urn'])->toBe('urn:test:ext1')
                ->and($result[1]['urn'])->toBe('urn:test:ext2')
                ->and($result[2]['urn'])->toBe('urn:test:ext3');
        });

        test('handles empty URN string gracefully', function (): void {
            // Arrange
            $extension = m::mock(ExtensionInterface::class);
            $extension->shouldReceive('getUrn')->andReturn('');
            $extension->shouldReceive('getSubscribedEvents')->andReturn([]);

            // Act
            $this->registry->register($extension);

            // Assert
            expect($this->registry->has(''))->toBeTrue()
                ->and($this->registry->get(''))->toBe($extension);
        });

        test('returns all registered extensions after replacing one with same URN', function (): void {
            // Arrange
            $ext1 = m::mock(ExtensionInterface::class);
            $ext1->shouldReceive('getUrn')->andReturn('urn:test:ext1');
            $ext1->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext2 = m::mock(ExtensionInterface::class);
            $ext2->shouldReceive('getUrn')->andReturn('urn:test:ext2');
            $ext2->shouldReceive('getSubscribedEvents')->andReturn([]);
            $ext2Replacement = m::mock(ExtensionInterface::class);
            $ext2Replacement->shouldReceive('getUrn')->andReturn('urn:test:ext2');
            $ext2Replacement->shouldReceive('getSubscribedEvents')->andReturn([]);

            $this->registry->register($ext1);
            $this->registry->register($ext2);
            $this->registry->register($ext2Replacement);

            // Act
            $result = $this->registry->all();

            // Assert
            expect($result)->toHaveCount(2)
                ->and($result['urn:test:ext2'])->toBe($ext2Replacement)
                ->and($result['urn:test:ext2'])->not->toBe($ext2);
        });
    });
});
