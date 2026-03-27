<?php

declare(strict_types=1);

namespace App\Modules\Requests\Application\UseCase;

use App\Modules\Requests\Domain\Repository\RequestTypeRepositoryInterface;

final readonly class GetRequestTypesUseCase
{
    public function __construct(
        private RequestTypeRepositoryInterface $typeRepository,
    ) {
    }

    public function execute(bool $onlyActive = true): array
    {
        $types = $onlyActive
            ? $this->typeRepository->findActive()
            : $this->typeRepository->findAll();

        return ['types' => $types];
    }
}
