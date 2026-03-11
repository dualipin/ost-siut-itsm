<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Repository;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Enum\DocumentTypeEnum;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;

    public function save(User $user, string $passwordHash): bool;

    public function updateProfile(
        int $userId,
        ?string $address,
        ?string $phone,
        ?string $email,
        ?string $photoPath,
        ?string $curp,
    ): bool;

    /**
     * @return array<string, string>
     */
    public function findDocumentsByUserId(int $userId): array;

    public function upsertDocument(
        int $userId,
        DocumentTypeEnum $documentType,
        string $filePath,
    ): bool;
}
