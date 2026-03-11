<?php

namespace App\Modules\Setting\Domain\Repository;

use App\Modules\Setting\Domain\Entity\Color;

interface SettingRepositoryInterface
{
    public function getColors(): ?Color;
    public function updateColors(Color $colors): void;
}
