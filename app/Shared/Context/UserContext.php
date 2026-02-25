<?php

namespace App\Shared\Context;

use App\Infrastructure\Session\SessionManager;
use App\Module\Auth\DTO\SessionUserDTO;
use App\Module\Auth\Enum\RoleEnum;

/**
 * @implements ContextInterface<SessionUserDTO>
 *
 * Almacena y recupera el contexto del usuario autenticado desde la sesión.
 */
final readonly class UserContext implements ContextInterface
{
    private const string SessionKey = "auth_user";

    public function __construct(private SessionManager $session) {}

    /**
     * Obtiene el usuario autenticado desde la sesión.
     *
     * @return SessionUserDTO|null
     */
    public function get(): ?SessionUserDTO
    {
        $data = $this->session->get(self::SessionKey);

        if (!$data || !is_array($data)) {
            return null;
        }

        // Validar que todos los campos requeridos existan
        if (!isset($data["id"]) || !isset($data["email"]) || !isset($data["rol"])) {
            return null;
        }

        return new SessionUserDTO(
            id: (int) $data["id"],
            email: $data["email"],
            rol: RoleEnum::tryFrom($data["rol"]),
        );
    }

    /**
     * Guarda el usuario autenticado en la sesión.
     *
     * @param SessionUserDTO $value
     */
    public function set($value): void
    {
        $this->session->set(self::SessionKey, [
            "id" => $value->id,
            "email" => $value->email,
            "rol" => $value->rol->value,
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
