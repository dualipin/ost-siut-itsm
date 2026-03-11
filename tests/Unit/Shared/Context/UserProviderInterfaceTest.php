<?php

declare(strict_types=1);

use App\Shared\Context\UserContextInterface;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Domain\Enum\RoleEnum;
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

function readCurrentUser(UserProviderInterface $provider): ?AuthenticatedUser
{
    return $provider->get();
}

it('returns null when there is no authenticated user', function (): void {
    $context = new InMemoryUserContext();

    expect(readCurrentUser($context))->toBeNull();
});

it('returns the authenticated user through the read-only contract', function (): void {
    $context = new InMemoryUserContext();

    $user = new AuthenticatedUser(
        id: 101,
        name: 'Test User',
        email: 'test@example.com',
        role: RoleEnum::Agremiado,
    );

    $context->set($user);

    expect(readCurrentUser($context))->toBe($user);
});

it('does not cache stale user state through the read-only contract', function (): void {
    $context = new InMemoryUserContext();

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
    expect(readCurrentUser($context))->toBe($first);

    $context->force($second);
    expect(readCurrentUser($context))->toBe($second);
});