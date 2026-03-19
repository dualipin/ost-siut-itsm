<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Application\UseCase;

use App\Modules\Transparency\Domain\Enum\TransparencyType;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;

final readonly class ListTransparenciesUseCase
{
    public function __construct(
        private TransparencyRepositoryInterface $repository
    ) {
    }

    /**
     * @return \App\Modules\Transparency\Domain\Entity\Transparency[]
     */
    public function executePublic(): array
    {
        return $this->repository->findAllPublic();
    }

    /**
     * @return \App\Modules\Transparency\Domain\Entity\Transparency[]
     */
    public function executePublicByType(TransparencyType $type): array
    {
        return $this->repository->findAllPublicByType($type);
    }

    /**
     * @return \App\Modules\Transparency\Domain\Entity\Transparency[]
     */
    public function executeForUser(int $userId): array
    {
        return $this->repository->findAllPermittedForUser($userId);
    }

    /**
     * @return \App\Modules\Transparency\Domain\Entity\Transparency[]
     */
    public function executeForUserByType(int $userId, TransparencyType $type): array
    {
        return $this->repository->findAllPermittedForUserByType($userId, $type);
    }
    
    /**
     * @return \App\Modules\Transparency\Domain\Entity\Transparency[]
     */
    public function executeAll(): array
    {
        return $this->repository->findAll();
    }

    /**
     * @return \App\Modules\Transparency\Domain\Entity\Transparency[]
     */
    public function executeAllByType(TransparencyType $type): array
    {
        return $this->repository->findAllByType($type);
    }
}
