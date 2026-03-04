<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Functions\Concerns\InteractsWithAuthentication;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Fakes\AuthenticatableUser;
use Tests\Support\Fakes\AuthenticationTestClass;

describe('InteractsWithAuthentication', function (): void {
    beforeEach(function (): void {
        // Create the test subject
        $this->subject = new AuthenticationTestClass();
    });

    describe('trait existence', function (): void {
        test('trait exists and can be used', function (): void {
            // Arrange & Act
            $trait = new ReflectionClass(InteractsWithAuthentication::class);

            // Assert
            expect($trait->isTrait())->toBeTrue();
        });

        test('test class properly uses the trait', function (): void {
            // Arrange & Act
            $reflection = new ReflectionClass(AuthenticationTestClass::class);
            $traits = $reflection->getTraitNames();

            // Assert
            expect($traits)->toContain(InteractsWithAuthentication::class);
        });
    });

    describe('getCurrentUser() - Happy Path', function (): void {
        test('returns authenticated user when user is logged in', function (): void {
            // Arrange
            $user = new AuthenticatableUser(
                id: 1,
                name: 'John Doe',
                email: 'john@example.com',
            );
            Auth::shouldReceive('check')->once()->andReturn(true);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->subject->testGetCurrentUser();

            // Assert
            expect($result)
                ->toBeInstanceOf(Authenticatable::class)
                ->and($result->getAuthIdentifier())->toBe(1)
                ->and($result->getName())->toBe('John Doe')
                ->and($result->getEmail())->toBe('john@example.com');
        });

        test('returns different authenticated users based on who is logged in', function (): void {
            // Arrange
            $userAlice = new AuthenticatableUser(
                id: 2,
                name: 'Alice Smith',
                email: 'alice@example.com',
            );
            Auth::shouldReceive('check')->once()->andReturn(true);
            Auth::shouldReceive('user')->once()->andReturn($userAlice);

            // Act
            $result = $this->subject->testGetCurrentUser();

            // Assert
            expect($result)
                ->toBeInstanceOf(Authenticatable::class)
                ->and($result->getAuthIdentifier())->toBe(2)
                ->and($result->getName())->toBe('Alice Smith')
                ->and($result->getEmail())->toBe('alice@example.com');
        });

        test('returns user with remember token when present', function (): void {
            // Arrange
            $user = new AuthenticatableUser(
                id: 3,
                name: 'Bob Johnson',
                email: 'bob@example.com',
                rememberToken: 'remember_token_123',
            );
            Auth::shouldReceive('check')->once()->andReturn(true);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->subject->testGetCurrentUser();

            // Assert
            expect($result)
                ->toBeInstanceOf(Authenticatable::class)
                ->and($result->getRememberToken())->toBe('remember_token_123');
        });
    });

    describe('getCurrentUser() - Sad Path', function (): void {
        test('throws HttpException with 401 when user is not authenticated', function (): void {
            // Arrange
            Auth::shouldReceive('check')->once()->andReturn(false);
            Auth::shouldReceive('user')->never();

            // Act & Assert
            expect($this->subject->testGetCurrentUser(...))
                ->toThrow(HttpException::class);
        });

        test('exception has correct status code 401', function (): void {
            // Arrange
            Auth::shouldReceive('check')->once()->andReturn(false);

            try {
                // Act
                $this->subject->testGetCurrentUser();
                $this->fail('Expected HttpException was not thrown');
            } catch (HttpException $httpException) {
                // Assert
                expect($httpException->getStatusCode())->toBe(401);
            }
        });

        test('exception has correct message "Unauthorized"', function (): void {
            // Arrange
            Auth::shouldReceive('check')->once()->andReturn(false);

            try {
                // Act
                $this->subject->testGetCurrentUser();
                $this->fail('Expected HttpException was not thrown');
            } catch (HttpException $httpException) {
                // Assert
                expect($httpException->getMessage())->toBe('Unauthorized');
            }
        });

        test('does not call auth()->user() when authentication fails', function (): void {
            // Arrange
            Auth::shouldReceive('check')->once()->andReturn(false);
            Auth::shouldReceive('user')->never();

            // Act & Assert
            try {
                $this->subject->testGetCurrentUser();
            } catch (HttpException) {
                // Exception is expected, test passes if Auth::user() was never called
            }
        });
    });

    describe('getCurrentUser() - Edge Cases', function (): void {
        test('calls auth() facade methods in correct order', function (): void {
            // Arrange
            $user = new AuthenticatableUser();
            $checkCalled = false;
            $userCalled = false;

            Auth::shouldReceive('check')
                ->once()
                ->andReturnUsing(function () use (&$checkCalled): true {
                    $checkCalled = true;

                    return true;
                });

            Auth::shouldReceive('user')
                ->once()
                ->andReturnUsing(function () use (&$userCalled, &$checkCalled, $user): AuthenticatableUser {
                    expect($checkCalled)->toBeTrue('check() should be called before user()');
                    $userCalled = true;

                    return $user;
                });

            // Act
            $this->subject->testGetCurrentUser();

            // Assert
            expect($checkCalled)->toBeTrue()
                ->and($userCalled)->toBeTrue();
        });

        test('works with different Authenticatable implementations', function (): void {
            // Arrange - Create an anonymous class implementing Authenticatable
            $customUser = new class() implements Authenticatable
            {
                public function getAuthIdentifierName(): string
                {
                    return 'custom_id';
                }

                public function getAuthIdentifier(): mixed
                {
                    return 'custom_123';
                }

                public function getAuthPassword(): string
                {
                    return 'custom_password';
                }

                public function getAuthPasswordName(): string
                {
                    return 'password';
                }

                public function getRememberToken(): ?string
                {
                    return null;
                }

                public function setRememberToken($value): void
                {
                    // Not implemented
                }

                public function getRememberTokenName(): string
                {
                    return 'remember_token';
                }
            };

            Auth::shouldReceive('check')->once()->andReturn(true);
            Auth::shouldReceive('user')->once()->andReturn($customUser);

            // Act
            $result = $this->subject->testGetCurrentUser();

            // Assert
            expect($result)
                ->toBeInstanceOf(Authenticatable::class)
                ->and($result->getAuthIdentifier())->toBe('custom_123')
                ->and($result->getAuthIdentifierName())->toBe('custom_id');
        });

        test('method is protected and not publicly accessible', function (): void {
            // Arrange
            $reflection = new ReflectionClass(AuthenticationTestClass::class);
            $method = $reflection->getMethod('getCurrentUser');

            // Act & Assert
            expect($method->isProtected())->toBeTrue()
                ->and($method->isPublic())->toBeFalse()
                ->and($method->isPrivate())->toBeFalse();
        });

        test('returns correct type hint of Authenticatable', function (): void {
            // Arrange
            $reflection = new ReflectionClass(AuthenticationTestClass::class);
            $method = $reflection->getMethod('getCurrentUser');
            $returnType = $method->getReturnType();

            // Act & Assert
            expect($returnType)->not->toBeNull()
                ->and($returnType->getName())->toBe(Authenticatable::class);
        });
    });

    describe('getCurrentUser() - Integration', function (): void {
        test('integrates properly with Laravel Auth facade', function (): void {
            // Arrange
            $user = new AuthenticatableUser(
                id: 100,
                name: 'Integration Test User',
                email: 'integration@test.com',
            );

            // Mock the global auth() helper behavior
            Auth::shouldReceive('check')->once()->andReturn(true);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->subject->testGetCurrentUser();

            // Assert
            expect($result)->toBe($user);
        });

        test('can be called multiple times with same result when user is authenticated', function (): void {
            // Arrange
            $user = new AuthenticatableUser();
            Auth::shouldReceive('check')->twice()->andReturn(true);
            Auth::shouldReceive('user')->twice()->andReturn($user);

            // Act
            $result1 = $this->subject->testGetCurrentUser();
            $result2 = $this->subject->testGetCurrentUser();

            // Assert
            expect($result1)->toBe($user)
                ->and($result2)->toBe($user)
                ->and($result1)->toBe($result2);
        });

        test('consistently throws exception when called multiple times without authentication', function (): void {
            // Arrange
            Auth::shouldReceive('check')->times(3)->andReturn(false);

            // Act & Assert
            for ($i = 0; $i < 3; ++$i) {
                expect($this->subject->testGetCurrentUser(...))
                    ->toThrow(HttpException::class);
            }
        });
    });
});
