<?php

namespace App\Shared\Context;

use App\Modules\Auth\Application\DTO\UserSession;

/**
 * @implements ContextInterface<UserSession>
 */
interface UserContextInterface extends ContextInterface
{
    public function isAuthenticated(): bool;
}
