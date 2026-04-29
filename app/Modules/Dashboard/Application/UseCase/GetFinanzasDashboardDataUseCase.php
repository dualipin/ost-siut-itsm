<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application\UseCase;

use App\Modules\Dashboard\Application\Service\AlertEvaluationService;
use App\Modules\Dashboard\Domain\Repository\FinanzasRepositoryInterface;
use App\Shared\Context\UserProviderInterface;
use DateTimeImmutable;

final readonly class GetFinanzasDashboardDataUseCase
{
    public function __construct(
        private FinanzasRepositoryInterface $finanzasRepository,
        private AlertEvaluationService $alertService,
    ) {}

    public function execute(UserProviderInterface $userProvider): array
    {
        $today = new DateTimeImmutable();
        $alerts = $this->alertService->evaluate($userProvider);

        $scheduledToday = $this->finanzasRepository->getScheduledPaymentsForDate($today);
        $totalScheduledToday = $this->finanzasRepository->getTotalScheduledForDate($today);

        return [
            'alerts' => $alerts,
            'kpis' => [
                [
                    'label' => 'Pagos Hoy',
                    'value' => count($scheduledToday),
                    'icon' => 'calendar-check',
                    'color' => 'primary',
                ],
                [
                    'label' => 'Por Cobrar Hoy',
                    'value' => '$' . number_format($totalScheduledToday, 2, ',', '.'),
                    'icon' => 'cash-coin',
                    'color' => 'success',
                ],
                [
                    'label' => 'Pagos Vencidos',
                    'value' => count($this->finanzasRepository->getOverduePayments()),
                    'icon' => 'exclamation-circle',
                    'color' => 'danger',
                ],
                [
                    'label' => 'Docs Leg. Pendientes',
                    'value' => count($this->finanzasRepository->getPendingLegalDocuments()),
                    'icon' => 'file-pdf',
                    'color' => 'warning',
                ],
            ],
            'scheduledPaymentsToday' => $scheduledToday,
            'totalScheduledToday' => $totalScheduledToday,
            'overduePayments' => $this->finanzasRepository->getOverduePayments(),
            'paymentsFortnight' => $this->finanzasRepository->getPaymentsNext15Days(),
            'pendingLegalDocs' => $this->finanzasRepository->getPendingLegalDocuments(),
            'pendingSignatures' => $this->finanzasRepository->getPendingUserSignatures(),
            'activeRestructurings' => $this->finanzasRepository->getActiveRestructurings(),
            'dashboardTitle' => 'Dashboard de Finanzas',
            'dashboardDescription' => 'Control y seguimiento del flujo financiero – Pagos, mora y reestructuraciones',
        ];
    }
}
