<?php

namespace App\Module\Auth\Middleware;

/**
 * Middleware de permisos - Requiere que el usuario tenga un permiso específico
 */
class PermissionMiddleware extends BaseMiddleware
{
    private array $requiredPermissions = [];
    private bool $requireAll = false;

    public function setRequiredPermissions(array $permissions, bool $requireAll = false): self
    {
        $this->requiredPermissions = $permissions;
        $this->requireAll = $requireAll;
        return $this;
    }

    public function execute(): bool
    {
        if (!$this->authService->isAuthenticated()) {
            return $this->deny('Debe estar autenticado para acceder a este recurso');
        }

        if (empty($this->requiredPermissions)) {
            return true;
        }

        if ($this->requireAll) {
            foreach ($this->requiredPermissions as $permission) {
                if (!$this->authService->hasPermission($permission)) {
                    return $this->deny(
                        'No tiene los permisos necesarios. Permiso requerido: ' . $permission
                    );
                }
            }
        } else {
            $hasPermission = false;
            foreach ($this->requiredPermissions as $permission) {
                if ($this->authService->hasPermission($permission)) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                return $this->deny(
                    'No tiene los permisos necesarios. Permisos requeridos: ' . implode(', ', $this->requiredPermissions)
                );
            }
        }

        return true;
    }
}
