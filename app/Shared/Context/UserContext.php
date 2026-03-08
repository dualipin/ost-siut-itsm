<?php

namespace App\Shared\Context;

use App\Infrastructure\Session\SessionInterface;
use App\Modules\Auth\Domain\Enum\RoleEnum;
use App\Shared\Security\AuthenticatedUser;

final readonly class UserContext implements UserContextInterface
{
    private const string SessionKey = "auth_user";

    public function __construct(private SessionInterface $session) {}

    public function get(): ?AuthenticatedUser
    {
        $data = $this->session->get(self::SessionKey);

        if (!is_array($data)) {
            return null;
        }

        if (
            !isset($data["id"]) ||
            !isset($data["email"]) ||
            !isset($data["role"])
        ) {
            return null;
        }

        $role = RoleEnum::tryFrom($data["role"]);

        if (!$role) {
            return null;
        }

        return new AuthenticatedUser(
            id: (int) $data["id"],
            email: $data["email"],
            role: $role,
        );
    }

    public function set(AuthenticatedUser $user): void
    {
        $this->session->set(self::SessionKey, [
            "id" => $user->id,
            "email" => $user->email,
            "role" => $user->role->value,
        ]);
    }

    public function isAuthenticated(): bool
    {
        return $this->get() !== null;
    }
}
