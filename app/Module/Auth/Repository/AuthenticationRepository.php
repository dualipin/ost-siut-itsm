<?php

namespace App\Module\Auth\Repository;

use App\Infrastructure\Persistence\BaseRepository;
use App\Module\Auth\DTO\AuthLogDTO;

/**
 * Implementación de repositorio de usuarios
 */
final class AuthenticationRepository extends BaseRepository
{
    public function saveResetToken(string $email, string $token): void
    {
        $stmt = $this->pdo->prepare(
            "insert into password_resets (email, token) values (:email, :token)",
        );
        $stmt->execute([
            ":email" => $email,
            ":token" => $token,
        ]);
    }

    public function saveAuthLog(AuthLogDTO $autenticacionLog): void
    {
        $stmt = $this->pdo->prepare("insert into autenticacion_logs 
            (usuario_id, email, action, ip_address, user_agent, error_message, success) 
            VALUES (:uid, :email, :action, :ip, :ua, :error, :success)");
        $stmt->execute([
            "uid" => $autenticacionLog->usuarioId,
            "email" => $autenticacionLog->email,
            "action" => $autenticacionLog->action->value,
            "ip" => $autenticacionLog->ipAddress,
            "ua" => $autenticacionLog->userAgent,
            "error" => $autenticacionLog->errorMessage,
            "success" => (int) $autenticacionLog->success,
        ]);
    }

    public function updateLastLogin(int $usuarioId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios SET ultimo_ingreso = NOW() WHERE usuario_id = :id",
        );
        $stmt->bindParam(":id", $usuarioId);
        $stmt->execute();
    }

    public function getCountLastFailedAttempts($email): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM autenticacion_logs 
              WHERE email = :email AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)",
        );
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
