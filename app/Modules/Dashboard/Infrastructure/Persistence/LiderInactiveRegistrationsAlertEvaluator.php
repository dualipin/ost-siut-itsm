<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Modules\Dashboard\Domain\Enum\AlertSeverityEnum;
use App\Modules\Dashboard\Domain\Repository\KpiLiderRepositoryInterface;
use App\Modules\Dashboard\Domain\Service\AlertEvaluatorInterface;
use App\Modules\Dashboard\Domain\VO\Alert;
use App\Shared\Context\UserProviderInterface;

final readonly class LiderInactiveRegistrationsAlertEvaluator implements AlertEvaluatorInterface
{
    public function __construct(
        private KpiLiderRepositoryInterface $kpiRepository,
    ) {}

    public function evaluate(UserProviderInterface $userProvider): array
    {
        $alerts = [];
        $recentUsers = $this->kpiRepository->getNewUsersLast30DaysByRole();

        // Check if no registrations in 60 days (empty recent data)
        if (empty($recentUsers)) {
            $alerts[] = new Alert(
                id: 'lider_no_registrations',
                title: 'Ausencia de Registros',
                description: 'No se han registrado nuevos usuarios en los últimos 60 días. Considere implementar estrategias de difusión.',
                severity: AlertSeverityEnum::Warning,
                actionLabel: 'Ver Estrategia',
                actionUrl: '/portal/admin/planning',
            );
        }

        return $alerts;
    }
}
