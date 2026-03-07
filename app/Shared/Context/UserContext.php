<?php

namespace App\Shared\Context;

use App\Infrastructure\Session\SessionInterface;
use App\Modules\Auth\Application\DTO\UserSession;
use App\Modules\Auth\Domain\Enum\RoleEnum;

/**
 * @implements ContextInterface<UserSession>
 *
 * Almacena y recupera el contexto del usuario autenticado desde la sesión.
 */
final readonly class UserContext implements UserContextInterface
{
    private const string SessionKey = "auth_user";

    public function __construct(private SessionInterface $session) {}

    /**
     * Obtiene el usuario autenticado desde la sesión.
     *
     * @return UserSession|null
     */
    public function get(): ?UserSession
    {
        $data = $this->session->get(self::SessionKey);

        if (!$data || !is_array($data)) {
            return null;
        }

        // Validar que todos los campos requeridos existan
        if (
            !isset($data["id"]) ||
            !isset($data["email"]) ||
            !isset($data["rol"])
        ) {
            return null;
        }

        return new UserSession(
            id: (int) $data["id"],
            email: $data["email"],
            role: RoleEnum::tryFrom($data["rol"]) ?? RoleEnum::Agremiado,
        );
    }

    /**
     * Guarda el usuario autenticado en la sesión.
     *
     * @param UserSession $value
     */
    public function set($value): void
    {
        $this->session->set(self::SessionKey, [
            "id" => $value->id,
            "email" => $value->email,
            "rol" => $value->role->value,
        ]);
    }

    /**
     * Elimina el usuario de la sesión (logout).
     */
    public function clear(): void
    {
        $this->session->remove(self::SessionKey);
    }

    /**
     * Indica si hay un usuario autenticado en sesión.
     */
    public function isAuthenticated(): bool
    {
        return $this->get() !== null;
    }
}
