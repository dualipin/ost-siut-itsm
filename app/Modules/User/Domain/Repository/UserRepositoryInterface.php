<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Repository;

use App\Modules\User\Application\DTO\UserSummary;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Enum\DocumentTypeEnum;
use App\Shared\Domain\Enum\RoleEnum;

interface UserRepositoryInterface
{
    /**
     * @return UserSummary[]
     */
    public function listado(bool $onlyActive = false): array;

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

    public function updateByAdmin(
        int $userId,
        string $name,
        string $surnames,
        string $email,
        RoleEnum $role,
        bool $active,
        ?string $curp,
        ?string $birthdate,
        ?string $address,
        ?string $phone,
        ?string $department,
        ?string $category,
        ?string $nss,
        float $salary,
        ?string $workStartDate,
    ): bool;

    public function deactivate(int $id): bool;

    /**
     * @return array<string, string>
     */
    public function findDocumentsByUserId(int $userId): array;

    /**
     * @return array<string, string>
     */
    public function findDocumentStatusesByUserId(int $userId): array;

    public function upsertDocument(
        int $userId,
        DocumentTypeEnum $documentType,
        string $filePath,
    ): bool;

    public function validateLatestDocumentByType(
        int $userId,
        DocumentTypeEnum $documentType,
        int $validatedBy,
    ): bool;

    public function rejectLatestDocumentByType(
        int $userId,
        DocumentTypeEnum $documentType,
        int $validatedBy,
    ): bool;
}
