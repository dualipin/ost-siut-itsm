<?php

namespace App\Modules\Auth\Domain\Service;

interface PasswordRecoveryNotifierInterface
{
    public function sendMagicLink(string $email, string $magicLink): void;
}