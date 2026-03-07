<?php

namespace App\Modules\Auth\Domain\Enum;

enum AuthLogActionEnum: string
{
    case LoginAttempt = "login_attempt";
    case Logout = "logout";
    case PasswordReset = "password_reset";
}
