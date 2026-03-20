<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application\DTO;

use App\Modules\Dashboard\Domain\VO\Alert;

final readonly class AlertCollection
{
    /**
     * @param Alert[] $alerts
     */
    public function __construct(
        public array $alerts,
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->alerts);
    }

    public function count(): int
    {
        return count($this->alerts);
    }

    public function hasCritical(): bool
    {
        foreach ($this->alerts as $alert) {
            if ($alert->severity->value === 'critical') {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Alert[]
     */
    public function getCritical(): array
    {
        return array_filter(
            $this->alerts,
            fn ($a) => $a->severity->value === 'critical'
        );
    }
}
