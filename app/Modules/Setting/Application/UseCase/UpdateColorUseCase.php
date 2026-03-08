<?php

namespace App\Modules\Setting\Application\UseCase;

use App\Modules\Setting\Application\Command\UpdateColorCommand;
use App\Modules\Setting\Domain\Repository\SettingRepositoryInterface;

final readonly class UpdateColorUseCase
{
    public function __construct(
        private SettingRepositoryInterface $repository,
    ) {}

    public function execute(UpdateColorCommand $command): void
    {
        $this->repository->updateColors($command->toColor());
    }
}
