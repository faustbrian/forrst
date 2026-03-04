<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Test implementation of Authenticatable for testing authentication traits.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class AuthenticatableUser implements Authenticatable
{
    public function __construct(
        private int $id = 1,
        private string $name = 'Test User',
        private string $email = 'test@example.com',
        private ?string $rememberToken = null,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return 'hashed_password';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken($value): void
    {
        // Not needed for tests
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
