<?php

namespace App\Modules\Auth\Domain\Repository;

use App\Modules\Auth\Domain\Entity\UserCredential;

interface CredentialRepositoryInterface
{
    public function findByEmail(string $email): ?UserCredential;

    public function updatePassword(int $userId, string $passwordHash): void;
}
