<?php

namespace App\Http\Middleware;

use App\Http\Exception\ForbiddenException;
use App\Http\Exception\UnauthorizedException;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Context\UserContext;

/**
 * Middleware de roles - Requiere que el usuario tenga al menos uno de los roles especificados
 *
 * Lanza UnauthorizedException si no está autenticado
 * Lanza ForbiddenException si está autenticado pero no tiene el rol requerido
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
     * @throws UnauthorizedException Si no está autenticado
     * @throws ForbiddenException Si está autenticado, pero no tiene el rol requerido
     */
    public function execute(): void
    {
        $session = $this->context->get(); // SessionUserDTO
        if ($session === null) {
            $this->deny("Debes iniciar sesión para acceder a este recurso.");
        }

        if (!in_array($session->role, $this->rolesPermitidos, strict: true)) {
            throw new ForbiddenException(
                "No tienes permisos para acceder a este recurso.",
            );
        }
    }
}
