<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application\UseCase;

use App\Modules\Dashboard\Application\DTO\AlertCollection;
use App\Modules\Dashboard\Application\Service\AlertEvaluationService;
use App\Modules\Dashboard\Domain\Repository\KpiLiderRepositoryInterface;
use App\Shared\Context\UserProviderInterface;

final readonly class GetLiderDashboardDataUseCase
{
    public function __construct(
        private KpiLiderRepositoryInterface $kpiRepository,
        private AlertEvaluationService $alertService,
    ) {}

    public function execute(UserProviderInterface $userProvider): array
    {
        $alerts = $this->alertService->evaluate($userProvider);

        return [
            'alerts' => $alerts,
            'kpis' => [
                [
                    'label' => 'Agremiados Activos',
                    'value' => $this->kpiRepository->getTotalActiveMembersCount(),
                    'icon' => 'people-fill',
                    'color' => 'primary',
                ],
                [
                    'label' => 'Cartera Total',
                    'value' => '$' . number_format($this->kpiRepository->getCarteTotalBalance(), 2, ',', '.'),
                    'icon' => 'cash-coin',
                    'color' => 'success',
                ],
                [
                    'label' => 'Tasa Recuperación (Mes)',
                    'value' => round($this->kpiRepository->getMonthlyRecoveryRate(), 1) . '%',
                    'icon' => 'graph-up',
                    'color' => 'info',
                ],
                [
                    'label' => 'Préstamos en Mora',
                    'value' => $this->kpiRepository->getLoansInDefaultCount(),
                    'icon' => 'exclamation-circle-fill',
                    'color' => 'danger',
                ],
            ],
            'portfolioEvolution' => $this->kpiRepository->getPortfolioEvolution(),
            'loansByStatus' => $this->kpiRepository->getLoansByStatus(),
            'topLoans' => $this->kpiRepository->getTop5LoansHighestBalance(),
            'newUsersByRole' => $this->kpiRepository->getNewUsersLast30DaysByRole(),
            'recentPublications' => $this->kpiRepository->getRecentPublications(),
            'dashboardTitle' => 'Dashboard del Líder',
            'dashboardDescription' => 'Visión estratégica de la salud organizacional del sindicato',
        ];
    }
}
