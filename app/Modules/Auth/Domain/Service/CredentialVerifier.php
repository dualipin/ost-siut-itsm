<?php

namespace App\Modules\Auth\Domain\Service;

use App\Modules\Auth\Domain\Entity\UserCredential;

final readonly class CredentialVerifier
{
    private const string DummyHash = '$2y$10$invalid.hash.to.prevent.timing.attacks';

    public function verify(?UserCredential $user, string $password): bool
    {
        if (!$user) {
            password_verify($password, self::DummyHash);
            return false;
        }

        return $user->verifyPassword($password);
    }
}
