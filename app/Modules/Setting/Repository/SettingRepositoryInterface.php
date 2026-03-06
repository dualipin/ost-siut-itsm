<?php

namespace App\Modules\Setting\Repository;

use App\Modules\Settings\Entity\Color;

interface SettingRepositoryInterface
{
    public function getColors(): ?Color;
    public function updateColors(Color $colors): void;
}
