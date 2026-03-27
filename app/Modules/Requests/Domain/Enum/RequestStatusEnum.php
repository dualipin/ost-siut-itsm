<?php

declare(strict_types=1);

namespace App\Modules\Requests\Domain\Enum;

enum RequestStatusEnum: string
{
    case PENDIENTE   = 'pendiente';
    case EN_REVISION = 'en_revision';
    case APROBADA    = 'aprobada';
    case RECHAZADA   = 'rechazada';
    case ENTREGADA   = 'entregada';
    case CANCELADA   = 'cancelada';

    public function label(): string
    {
        return match($this) {
            self::PENDIENTE   => 'Pendiente',
            self::EN_REVISION => 'En Revisión',
            self::APROBADA    => 'Aprobada',
            self::RECHAZADA   => 'Rechazada',
            self::ENTREGADA   => 'Entregada',
            self::CANCELADA   => 'Cancelada',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::PENDIENTE   => 'bg-warning-subtle text-warning',
            self::EN_REVISION => 'bg-info-subtle text-info',
            self::APROBADA    => 'bg-success-subtle text-success',
            self::RECHAZADA   => 'bg-danger-subtle text-danger',
            self::ENTREGADA   => 'bg-primary-subtle text-primary',
            self::CANCELADA   => 'bg-secondary-subtle text-secondary',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::RECHAZADA, self::ENTREGADA, self::CANCELADA], true);
    }

    /**
     * @return RequestStatusEnum[]
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::PENDIENTE   => [self::EN_REVISION, self::CANCELADA],
            self::EN_REVISION => [self::APROBADA, self::RECHAZADA],
            self::APROBADA    => [self::ENTREGADA],
            default           => [],
        };
    }
}
