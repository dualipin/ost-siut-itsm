<?php

namespace App\Shared\Context;

use App\Shared\Security\AuthenticatedUser;

/**
 * @extends ContextInterface<AuthenticatedUser>
 */
interface UserProviderInterface extends ContextInterface
{
    public function get(): ?AuthenticatedUser;
}