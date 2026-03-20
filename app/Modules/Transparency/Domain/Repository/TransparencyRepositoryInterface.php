<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Domain\Repository;

use App\Modules\Transparency\Domain\Entity\Transparency;
use App\Modules\Transparency\Domain\Entity\TransparencyAttachment;
use App\Modules\Transparency\Domain\Entity\TransparencyPermission;
use App\Modules\Transparency\Domain\Enum\TransparencyType;

interface TransparencyRepositoryInterface
{
    /**
     * @return Transparency[]
     */
    public function findAllPublic(): array;

    /**
     * @return Transparency[]
     */
    public function findAllPublicByType(TransparencyType $type): array;

    /**
     * @return Transparency[]
     */
    public function findAllPermittedForUser(int $userId): array;

    /**
     * @return Transparency[]
     */
    public function findAllPublicOrPermittedForUser(int $userId): array;

    /**
     * @return Transparency[]
     */
    public function findAllPermittedForUserByType(int $userId, TransparencyType $type): array;

    /**
     * @return Transparency[]
     */
    public function findAllPublicOrPermittedForUserByType(int $userId, TransparencyType $type): array;

    /**
     * @return Transparency[]
     */
    public function findAll(): array;

    /**
     * @return Transparency[]
     */
    public function findAllByType(TransparencyType $type): array;

    public function findById(int $id): ?Transparency;

    public function save(Transparency $transparency): Transparency;

    public function delete(int $id): void;
    
    // Rutinas para adjuntos
    public function saveAttachment(TransparencyAttachment $attachment): TransparencyAttachment;
    public function findAttachmentsByTransparencyId(int $transparencyId): array;
    public function deleteAttachment(int $attachmentId): void;
    
    // Rutinas para permisos
    public function grantPermission(TransparencyPermission $permission): void;
    public function revokePermission(int $transparencyId, int $userId): void;
    public function findPermissionsByTransparencyId(int $transparencyId): array;
}
