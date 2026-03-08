<?php

namespace App\Shared\Context;

use App\Shared\Security\AuthenticatedUser;

/**
 * @extends ContextInterface<AuthenticatedUser>
 */
interface UserContextInterface extends ContextInterface
{
    public function set(AuthenticatedUser $user): void;

    public function isAuthenticated(): bool;
}
