<?php

namespace App\Modules\Loan\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Loan\Domain\Repository\SaverUserRepositoryInterface;

final class PdoSaverUserRepository extends PdoBaseRepository implements SaverUserRepositoryInterface
{
    public function isSaver(int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM saver_users 
            WHERE user_id = :user_id AND active = 1
        ");
        $stmt->execute(['user_id' => $userId]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    public function addSaver(int $userId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO saver_users (user_id, enrollment_date, active)
            VALUES (:user_id, NOW(), 1)
            ON DUPLICATE KEY UPDATE active = 1, enrollment_date = NOW()
        ");
        $stmt->execute(['user_id' => $userId]);
    }

    public function removeSaver(int $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE saver_users SET active = 0 WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
    }

    public function findAllActiveSavers(): array
    {
        $stmt = $this->pdo->query("
            SELECT user_id FROM saver_users WHERE active = 1
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
