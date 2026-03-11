<?php

namespace App\Shared\Provider;

use App\Shared\Context\UserContextInterface;
use App\Shared\Security\AuthenticatedUser;

/**
 * Provee el usuario autenticado del request actual sin acoplarse a persistencia.
 *
 * @implements ContextProviderInterface<AuthenticatedUser>
 */
final readonly class UserContextProvider implements ContextProviderInterface
{
    public function __construct(
        private UserContextInterface $context,
    ) {}

    public function get(): ?AuthenticatedUser
    {
        return $this->context->get();
    }
}
