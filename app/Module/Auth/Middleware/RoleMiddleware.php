<?php

namespace App\Module\Auth\Middleware;

/**
 * Middleware de roles - Requiere que el usuario tenga al menos uno de los roles especificados
 */
class RoleMiddleware extends BaseMiddleware
{
    private array $requiredRoles = [];

    public function setRequiredRoles(array $roles): self
    {
        $this->requiredRoles = $roles;
        return $this;
    }

    public function execute(): bool
    {
        if (!$this->authService->isAuthenticated()) {
            return $this->deny('Debe estar autenticado para acceder a este recurso');
        }

        if (empty($this->requiredRoles)) {
            return true;
        }

        if (!$this->authService->hasAnyRole($this->requiredRoles)) {
            return $this->deny(
                'No tiene los permisos necesarios. Roles requeridos: ' . implode(', ', $this->requiredRoles)
            );
        }

        return true;
    }
}
