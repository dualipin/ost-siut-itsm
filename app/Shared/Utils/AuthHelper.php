<?php

namespace App\Shared\Utils;

use App\Infrastructure\Session\SessionManager;
use App\Module\Usuario\Repository\UsuarioRepository;

/**
 * Utilidad para obtener el usuario actualmente autenticado desde la sesión
 */
final class AuthHelper
{
    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly UsuarioRepository $usuarioRepository,
    ) {}

    /**
     * Obtiene el usuario actualmente autenticado desde la sesión
     * @return \App\Module\Usuario\DTO\UsuarioSimpleDTO|null
     */
    public function getAuthenticatedUser()
    {
        $userId = $this->sessionManager->get("user_id");
        
        if (!$userId) {
            return null;
        }

        // Obtener el usuario con todos sus datos del listado
        $usuarios = $this->usuarioRepository->listado();
        
        foreach ($usuarios as $usuario) {
            if ((int)$usuario->id === (int)$userId) {
                return $usuario;
            }
        }
        
        return null;
    }

    /**
     * Verifica si hay usuario autenticado
     */
    public function isAuthenticated(): bool
    {
        return $this->sessionManager->get("user_id") !== null;
    }

    /**
     * Obtiene el ID del usuario autenticado
     */
    public function getUserId(): ?int
    {
        return $this->sessionManager->get("user_id");
    }

    /**
     * Obtiene el email del usuario autenticado
     */
    public function getUserEmail(): ?string
    {
        return $this->sessionManager->get("user_email");
    }
}
