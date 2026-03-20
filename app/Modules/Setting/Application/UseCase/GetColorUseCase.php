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
            primary: "#611232",
            secondary: "#a57f2c",
            success: "#38b44a",
            info: "#17a2b8",
            warning: "#efb73e",
            danger: "#df382c",
            light: "#e9ecef",
            dark: "#002f2a",
            white: "#ffffff",
            body: "#212529",
            bodyBackground: "#f8f9fa",
        );
    }
}
