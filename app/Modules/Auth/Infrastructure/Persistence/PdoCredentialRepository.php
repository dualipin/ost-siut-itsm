<?php

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Auth\Domain\Entity\UserCredential;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Shared\Domain\Enum\RoleEnum;

class PdoCredentialRepository extends PdoBaseRepository implements
    CredentialRepositoryInterface
{
    public function findByEmail(string $email): ?UserCredential
    {
        $stmt = $this->pdo->prepare("
        SELECT user_id, email, password_hash, active, role FROM users WHERE email = :email
        ");

        $stmt->execute(["email" => $email]);

        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        return new UserCredential(
            id: $result["user_id"],
            email: $result["email"],
            passwordHash: $result["password_hash"],
            role: RoleEnum::tryFrom($result["role"]) ?? RoleEnum::NoAgremiado,
            isActive: (bool) $result["active"],
        );
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET password_hash = :password WHERE user_id = :id",
        );
        $stmt->execute([
            ":password" => $passwordHash,
            ":id" => $userId,
        ]);
    }
}
