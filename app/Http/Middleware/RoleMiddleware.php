<?php

namespace App\Http\Middleware;

use App\Shared\Context\ContextInterface;

/**
 * Middleware de roles - Requiere que el usuario tenga al menos uno de los roles especificados
 */
final class RoleMiddleware extends BaseMiddleware
{
    public function __construct(
        ContextInterface $context,
        private readonly AuthMiddleware $authMiddleware,
    ) {
        parent::__construct($context);
    }
    private array $requiredRoles = [];

    public function setRequiredRoles(array $roles): self
    {
        $this->requiredRoles = $roles;
        return $this;
    }

    public function execute(): bool
    {
        if (!$this->context->get()) {
            return $this->deny(
                "Debe estar autenticado para acceder a este recurso",
            );
        }

        if (empty($this->requiredRoles)) {
            return true;
        }

        if (!$this->context->get($this->requiredRoles)) {
            return $this->deny(
                "No tiene los permisos necesarios. Roles requeridos: " .
                    implode(", ", $this->requiredRoles),
            );
        }

        return true;
    }
}
