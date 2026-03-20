<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain\Enum;

enum AlertSeverityEnum: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::Critical => 'badge-danger',
            self::Warning => 'badge-warning',
            self::Info => 'badge-info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Critical => 'exclamation-triangle-fill',
            self::Warning => 'exclamation-circle-fill',
            self::Info => 'info-circle-fill',
        };
    }
}
