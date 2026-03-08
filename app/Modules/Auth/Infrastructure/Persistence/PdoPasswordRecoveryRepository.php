<?php

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Auth\Domain\Repository\PasswordRecoveryInterface;
use Throwable;

final class PdoPasswordRecoveryRepository extends PdoBaseRepository implements
    PasswordRecoveryInterface
{
    /**
     * @throws Throwable
     */
    public function storeMagicLink(string $email, string $token): void
    {
        $this->pdo->beginTransaction();

        try {
            $deleteStmt = $this->pdo->prepare(
                "DELETE FROM password_resets WHERE email = :email",
            );
            $deleteStmt->execute(["email" => $email]);

            $insertStmt = $this->pdo->prepare(
                "INSERT INTO password_resets (email, token, created_at) VALUES (:email, UNHEX(:token), NOW())",
            );
            $insertStmt->execute([
                "email" => $email,
                "token" => $token,
            ]);

            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    public function findEmailByValidToken(
        string $token,
        int $ttlMinutes,
    ): ?string {
        $stmt = $this->pdo->prepare(
            "SELECT email FROM password_resets WHERE token = UNHEX(:token) AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) <= :ttl LIMIT 1",
        );
        $stmt->bindValue(":token", $token);
        $stmt->bindValue(":ttl", $ttlMinutes, \PDO::PARAM_INT);
        $stmt->execute();

        $email = $stmt->fetchColumn();

        return is_string($email) ? $email : null;
    }

    public function consumeToken(string $token): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM password_resets WHERE token = UNHEX(:token)",
        );
        $stmt->execute(["token" => $token]);
    }
}
