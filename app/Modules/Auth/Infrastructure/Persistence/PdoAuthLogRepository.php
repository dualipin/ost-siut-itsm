<?php

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Auth\Domain\Entity\AuthLog;
use App\Modules\Auth\Domain\Repository\AuthLogRepositoryInterface;

final class PdoAuthLogRepository extends PdoBaseRepository implements
    AuthLogRepositoryInterface
{
    public function saveAuthLog(AuthLog $log): void
    {
        $stmt = $this->pdo->prepare("insert into auth_logs 
            (user_id, email, action, ip_address, user_agent, error_message, success) 
            VALUES (:uid, :email, :action, :ip, :ua, :error, :success)");
        $stmt->execute([
            "uid" => $log->userId,
            "email" => $log->email,
            "action" => $log->action->value,
            "ip" => $log->ipAddress,
            "ua" => $log->userAgent,
            "error" => $log->errorMessage,
            "success" => (int) $log->success,
        ]);
    }
    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET last_entry = NOW() WHERE user_id = :id",
        );
        $stmt->bindParam(":id", $userId);
        $stmt->execute();
    }

    public function getCountLastFailedAttempts(
        string $email,
        int $minutes = 15,
    ): int {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM auth_logs 
              WHERE email = :email AND success = false AND created_at > (NOW() - INTERVAL :interval MINUTE)",
        );
        $stmt->execute([
            "email" => $email,
            "interval" => $minutes,
        ]);

        return (int) $stmt->fetchColumn();
    }
}
