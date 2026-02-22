<?php

namespace App\Http\Middleware;

use App\Module\Auth\Enum\RolEnum;
use App\Shared\Context\ContextInterface;
use App\Shared\Context\UserContext;

/**
 * Middleware de roles - Requiere que el usuario tenga al menos uno de los roles especificados
 */
final class RoleMiddleware extends BaseMiddleware
{
    /** @var RolEnum[] */
    private array $rolesPermitidos;
    public function __construct(UserContext $context, RolEnum ...$roles)
    {
        parent::__construct($context);
        $this->rolesPermitidos = $roles;
    }

    public function execute(): bool
    {
        $session = $this->context->get(); // SessionUserDTO
        if ($session === null) {
            return $this->deny(
                "Debes iniciar sesión para acceder a este recurso.",
            );
        }

        if (!in_array($session->rol, $this->rolesPermitidos, strict: true)) {
            return $this->deny(
                "No tienes permisos para acceder a este recurso.",
            );
        }

        return true;
    }
}
