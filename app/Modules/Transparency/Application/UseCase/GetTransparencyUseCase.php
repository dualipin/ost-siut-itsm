<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Application\UseCase;

use App\Modules\Transparency\Domain\Entity\Transparency;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;

final readonly class GetTransparencyUseCase
{
    public function __construct(
        private TransparencyRepositoryInterface $repository
    ) {
    }

    public function execute(int $id): Transparency
    {
        $transparency = $this->repository->findById($id);
        
        if ($transparency === null) {
            throw TransparencyNotFoundException::withId($id);
        }

        return $transparency;
    }
}
