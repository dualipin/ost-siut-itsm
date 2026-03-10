<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Repository;

use App\Modules\User\Domain\Entity\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;

    public function save(User $user, string $passwordHash): bool;
}
