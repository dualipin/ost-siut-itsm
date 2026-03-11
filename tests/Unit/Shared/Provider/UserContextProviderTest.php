<?php

declare(strict_types=1);

use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Provider\UserContextProvider;
use App\Shared\Security\AuthenticatedUser;

final class InMemoryUserContext implements UserContextInterface
{
    private ?AuthenticatedUser $user = null;

    public function get(): ?AuthenticatedUser
    {
        return $this->user;
    }

    public function set(AuthenticatedUser $user): void
    {
        $this->user = $user;
    }

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    public function force(?AuthenticatedUser $user): void
    {
        $this->user = $user;
    }
}

it('returns null when there is no authenticated user', function (): void {
    $context = new InMemoryUserContext();
    $provider = new UserContextProvider($context);

    expect($provider->get())->toBeNull();
});

it('returns the authenticated user from context', function (): void {
    $context = new InMemoryUserContext();
    $provider = new UserContextProvider($context);

    $user = new AuthenticatedUser(
        id: 101,
        name: 'Test User',
        email: 'test@example.com',
        role: RoleEnum::Agremiado,
    );

    $context->set($user);

    expect($provider->get())->toBe($user);
});

it('does not cache stale user state', function (): void {
    $context = new InMemoryUserContext();
    $provider = new UserContextProvider($context);

    $first = new AuthenticatedUser(
        id: 1,
        name: 'First User',
        email: 'first@example.com',
        role: RoleEnum::Admin,
    );

    $second = new AuthenticatedUser(
        id: 2,
        name: 'Second User',
        email: 'second@example.com',
        role: RoleEnum::Lider,
    );

    $context->force($first);
    expect($provider->get())->toBe($first);

    $context->force($second);
    expect($provider->get())->toBe($second);
});
