<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain\VO;

use App\Modules\Dashboard\Domain\Enum\AlertSeverityEnum;

final readonly class Alert
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public AlertSeverityEnum $severity,
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public int $affectedCount = 0,
    ) {}
}
