<?php

namespace App\Module\Auth\Enum;

enum AuthLogActionEnum: string
{
    case LoginAttempt = "login_attempt";
    case Logout = "logout";
    case PasswordReset = "password_reset";
}
