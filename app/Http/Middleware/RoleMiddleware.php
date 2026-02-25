<?php

namespace App\Http\Middleware;

use App\Http\Exception\UnauthorizedException;
use App\Module\Auth\Enum\RoleEnum;
use App\Shared\Context\UserContext;

/**
 * Middleware de roles - Requiere que el usuario tenga al menos uno de los roles especificados
 */
final class RoleMiddleware extends BaseMiddleware
{
    /** @var RoleEnum[] */
    private array $rolesPermitidos;
    public function __construct(UserContext $context, RoleEnum ...$roles)
    {
        parent::__construct($context);
        $this->rolesPermitidos = $roles;
    }

    /**
     * @throws UnauthorizedException
     */
    public function execute(): void
    {
        $session = $this->context->get(); // SessionUserDTO
        if ($session === null) {
            $this->deny("Debes iniciar sesión para acceder a este recurso.");
        }

        if (!in_array($session->rol, $this->rolesPermitidos, strict: true)) {
            $this->deny("No tienes permisos para acceder a este recurso.");
        }
    }
}
