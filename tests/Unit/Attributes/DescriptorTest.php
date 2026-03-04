<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Attributes\Descriptor;
use Cline\Forrst\Contracts\DescriptorInterface;
use Cline\Forrst\Discovery\FunctionDescriptor;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestDescriptor implements DescriptorInterface
{
    public static function create(): FunctionDescriptor
    {
        return FunctionDescriptor::make()
            ->urn('urn:app:forrst:fn:test:function')
            ->summary('A test function');
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Descriptor(TestDescriptor::class)]
final class TestFunction {}

describe('Descriptor Attribute', function (): void {
    describe('Happy Paths', function (): void {
        test('creates attribute with class string', function (): void {
            // Act
            $attribute = new Descriptor(TestDescriptor::class);

            // Assert
            expect($attribute->class)->toBe(TestDescriptor::class);
        });

        test('is a PHP attribute targeting classes', function (): void {
            // Arrange
            $reflection = new ReflectionClass(Descriptor::class);
            $attributes = $reflection->getAttributes(Attribute::class);

            // Assert
            expect($attributes)->toHaveCount(1);

            $attributeInstance = $attributes[0]->newInstance();
            expect($attributeInstance->flags)->toBe(Attribute::TARGET_CLASS);
        });

        test('can be read from class reflection', function (): void {
            // Arrange
            $reflection = new ReflectionClass(TestFunction::class);
            $attributes = $reflection->getAttributes(Descriptor::class);

            // Assert
            expect($attributes)->toHaveCount(1);

            $descriptor = $attributes[0]->newInstance();
            expect($descriptor->class)->toBe(TestDescriptor::class);
        });

        test('descriptor class can be instantiated and called', function (): void {
            // Arrange
            $reflection = new ReflectionClass(TestFunction::class);
            $attributes = $reflection->getAttributes(Descriptor::class);
            $descriptorAttribute = $attributes[0]->newInstance();

            // Act
            $descriptorClass = $descriptorAttribute->class;
            $functionDescriptor = $descriptorClass::create();

            // Assert
            expect($functionDescriptor)->toBeInstanceOf(FunctionDescriptor::class)
                ->and($functionDescriptor->getUrn())->toBe('urn:app:forrst:fn:test:function')
                ->and($functionDescriptor->getSummary())->toBe('A test function');
        });
    });

    describe('Edge Cases', function (): void {
        test('attribute stores exact class string', function (): void {
            // Arrange
            $className = TestDescriptor::class;

            // Act
            $attribute = new Descriptor($className);

            // Assert
            expect($attribute->class)->toBe($className)
                ->and(class_exists($attribute->class))->toBeTrue();
        });

        test('class property is readonly', function (): void {
            // Arrange
            $reflection = new ReflectionClass(Descriptor::class);
            $property = $reflection->getProperty('class');

            // Assert
            expect($property->isReadOnly())->toBeTrue();
        });
    });
});
