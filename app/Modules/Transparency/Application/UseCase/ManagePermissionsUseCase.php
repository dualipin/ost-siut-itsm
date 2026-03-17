<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Transparency\Domain\Entity\TransparencyPermission;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;

final readonly class ManagePermissionsUseCase
{
    public function __construct(
        private TransparencyRepositoryInterface $repository,
        private TransactionManager $transactionManager
    ) {
    }

    public function execute(int $transparencyId, array $userIds): void
    {
        $transparency = $this->repository->findById($transparencyId);
        if ($transparency === null) {
            throw TransparencyNotFoundException::withId($transparencyId);
        }

        $this->transactionManager->transactional(function () use ($transparencyId, $userIds) {
            // Eliminar permisos actuales
            $currentPermissions = $this->repository->findPermissionsByTransparencyId($transparencyId);
            foreach ($currentPermissions as $permission) {
                $this->repository->revokePermission($transparencyId, $permission->userId);
            }

            // Asignar nuevos permisos
            foreach ($userIds as $userId) {
                $newPermission = new TransparencyPermission(null, $transparencyId, (int) $userId);
                $this->repository->grantPermission($newPermission);
            }
        });
    }
}
