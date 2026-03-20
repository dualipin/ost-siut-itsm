<?php

namespace App\Modules\Setting\Application\UseCase;

use App\Modules\Setting\Domain\Repository\SettingRepositoryInterface;

final readonly class ResetColorUseCase
{
    public function __construct(
        private SettingRepositoryInterface $repository,
    ) {}

    public function execute(): void
    {
        $this->repository->resetColors();
    }
}
