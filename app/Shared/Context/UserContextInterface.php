<?php

namespace App\Shared\Context;

use App\Shared\Security\AuthenticatedUser;

/**
 * @extends UserProviderInterface
 */
interface UserContextInterface extends UserProviderInterface
{
    public function set(AuthenticatedUser $user): void;

    public function isAuthenticated(): bool;
}
