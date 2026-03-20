<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Modules\Dashboard\Domain\Enum\AlertSeverityEnum;
use App\Modules\Dashboard\Domain\Repository\FinanzasRepositoryInterface;
use App\Modules\Dashboard\Domain\Service\AlertEvaluatorInterface;
use App\Modules\Dashboard\Domain\VO\Alert;
use App\Shared\Context\UserProviderInterface;

final readonly class FinanzasPendingDocumentsAlertEvaluator implements AlertEvaluatorInterface
{
    public function __construct(
        private FinanzasRepositoryInterface $finanzasRepository,
    ) {}

    public function evaluate(UserProviderInterface $userProvider): array
    {
        $alerts = [];
        $pendingDocs = $this->finanzasRepository->getPendingLegalDocuments();

        if (count($pendingDocs) > 0) {
            // Critical if any doc > 48 hours
            $criticalCount = count(array_filter($pendingDocs, fn($d) => $d['days_waiting'] > 2));

            $alerts[] = new Alert(
                id: 'finanzas_pending_legal_docs',
                title: 'Documentos Legales Pendientes de Validación',
                description: count($pendingDocs) . " documento(s) legal(es) esperando validación. $criticalCount llevan más de 48 horas.",
                severity: $criticalCount > 0 ? AlertSeverityEnum::Critical : AlertSeverityEnum::Warning,
                actionLabel: 'Ver Documentos',
                actionUrl: '/portal/finanzas/documentos?status=pending',
                affectedCount: count($pendingDocs),
            );
        }

        return $alerts;
    }
}
