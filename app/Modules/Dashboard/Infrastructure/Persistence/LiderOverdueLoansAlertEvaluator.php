<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Modules\Dashboard\Domain\Enum\AlertSeverityEnum;
use App\Modules\Dashboard\Domain\Repository\KpiLiderRepositoryInterface;
use App\Modules\Dashboard\Domain\Service\AlertEvaluatorInterface;
use App\Modules\Dashboard\Domain\VO\Alert;
use App\Shared\Context\UserProviderInterface;

final readonly class LiderOverdueLoansAlertEvaluator implements AlertEvaluatorInterface
{
    public function __construct(
        private KpiLiderRepositoryInterface $kpiRepository,
    ) {}

    public function evaluate(UserProviderInterface $userProvider): array
    {
        $alerts = [];
        $overdueCount = $this->kpiRepository->getLoansInDefaultCount();

        if ($overdueCount > 0) {
            $alerts[] = new Alert(
                id: 'lider_overdue_loans',
                title: 'Préstamos con Mora Crítica',
                description: "Hay $overdueCount préstamo(s) con más de 30 días de mora. Se requiere revisión inmediata.",
                severity: AlertSeverityEnum::Critical,
                actionLabel: 'Ver Detalles',
                actionUrl: '/portal/prestamos/activos',
                affectedCount: $overdueCount,
            );
        }

        return $alerts;
    }
}
