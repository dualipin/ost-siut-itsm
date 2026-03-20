<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Modules\Dashboard\Domain\Enum\AlertSeverityEnum;
use App\Modules\Dashboard\Domain\Repository\DashboardAdministradorRepositoryInterface;
use App\Modules\Dashboard\Domain\Service\AlertEvaluatorInterface;
use App\Modules\Dashboard\Domain\VO\Alert;
use App\Shared\Context\UserProviderInterface;

final readonly class AdminPendingDocumentsAlertEvaluator implements AlertEvaluatorInterface
{
    public function __construct(
        private DashboardAdministradorRepositoryInterface $adminRepository,
    ) {}

    public function evaluate(UserProviderInterface $userProvider): array
    {
        $alerts = [];
        $pendingDocs = $this->adminRepository->getPendingDocuments();

        if (count($pendingDocs) > 0) {
            // Critical if any doc has been waiting > 48 hours
            $criticalCount = count(array_filter($pendingDocs, fn($d) => $d['days_waiting'] > 2));

            $alerts[] = new Alert(
                id: 'admin_pending_docs',
                title: 'Documentos Pendientes de Validación',
                description: count($pendingDocs) . " documento(s) esperando validación. $criticalCount llevan más de 48 horas.",
                severity: $criticalCount > 0 ? AlertSeverityEnum::Critical : AlertSeverityEnum::Warning,
                actionLabel: 'Ver Cola',
                actionUrl: '/portal/admin/documentos?status=pendiente',
                affectedCount: count($pendingDocs),
            );
        }

        return $alerts;
    }
}
