<?php

namespace App\Modules\Auth\Domain\Repository;

use App\Modules\Auth\Domain\Entity\AuthLog;

interface AuthLogRepositoryInterface
{
    public function saveAuthLog(AuthLog $log): void;
    public function updateLastLogin(int $userId): void;
    public function getCountLastFailedAttempts(
        string $email,
        int $minutes = 15,
    ): int;
}
