<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Modules\Dashboard\Domain\Enum\AlertSeverityEnum;
use App\Modules\Dashboard\Domain\Repository\FinanzasRepositoryInterface;
use App\Modules\Dashboard\Domain\Service\AlertEvaluatorInterface;
use App\Modules\Dashboard\Domain\VO\Alert;
use App\Shared\Context\UserProviderInterface;

final readonly class FinanzasOverduePaymentsAlertEvaluator implements AlertEvaluatorInterface
{
    public function __construct(
        private FinanzasRepositoryInterface $finanzasRepository,
    ) {}

    public function evaluate(UserProviderInterface $userProvider): array
    {
        $alerts = [];
        $overduePayments = $this->finanzasRepository->getOverduePayments();

        if (count($overduePayments) > 0) {
            // Critical if any payment > 5 days overdue
            $criticalCount = count(array_filter($overduePayments, fn($p) => $p['days_overdue'] > 5));

            $alerts[] = new Alert(
                id: 'finanzas_overdue_payments',
                title: 'Pagos Vencidos Sin Registrar',
                description: count($overduePayments) . " pago(s) vencido(s). $criticalCount llevan más de 5 días.",
                severity: $criticalCount > 0 ? AlertSeverityEnum::Critical : AlertSeverityEnum::Warning,
                actionLabel: 'Ver Pendientes',
                actionUrl: '/portal/finanzas/pagos?filter=overdue',
                affectedCount: count($overduePayments),
            );
        }

        return $alerts;
    }
}
