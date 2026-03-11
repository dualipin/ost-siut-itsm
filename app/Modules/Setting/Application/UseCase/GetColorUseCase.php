<?php

namespace App\Modules\Setting\Application\UseCase;

use App\Modules\Setting\Domain\Entity\Color;
use App\Modules\Setting\Domain\Repository\SettingRepositoryInterface;

final readonly class GetColorUseCase
{
    public function __construct(
        private SettingRepositoryInterface $repository,
    ) {}

    public function execute(): Color
    {
        return $this->repository->getColors() ?? new Color(
            primary: "#0d6efd",
            secondary: "#6c757d",
            success: "#198754",
            info: "#0dcaf0",
            warning: "#ffc107",
            danger: "#dc3545",
            light: "#f8f9fa",
            dark: "#212529",
        );
    }
}
