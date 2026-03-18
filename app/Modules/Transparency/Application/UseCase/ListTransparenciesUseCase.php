<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Application\UseCase;

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
    public function executeForUser(int $userId): array
    {
        return $this->repository->findAllPermittedForUser($userId);
    }
    
    /**
     * @return \App\Modules\Transparency\Domain\Entity\Transparency[]
     */
    public function executeAll(): array
    {
        return $this->repository->findAll();
    }
}
