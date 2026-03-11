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
        SELECT
            user_id,
            email,
            password_hash,
            active,
            role,
            COALESCE(NULLIF(TRIM(CONCAT_WS(' ', name, surnames)), ''), email) AS display_name
        FROM users
        WHERE email = :email
        LIMIT 1
        ");

        $stmt->execute(["email" => $email]);

        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        return new UserCredential(
            id: $result["user_id"],
            name: $result["display_name"],
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
