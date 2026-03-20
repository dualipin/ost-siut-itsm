<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application\UseCase;

use App\Modules\Dashboard\Application\Service\AlertEvaluationService;
use App\Modules\Dashboard\Domain\Repository\DashboardAdministradorRepositoryInterface;
use App\Shared\Context\UserProviderInterface;

final readonly class GetAdministradorDashboardDataUseCase
{
    public function __construct(
        private DashboardAdministradorRepositoryInterface $adminRepository,
        private AlertEvaluationService $alertService,
    ) {}

    public function execute(UserProviderInterface $userProvider): array
    {
        $alerts = $this->alertService->evaluate($userProvider);

        return [
            'alerts' => $alerts,
            'kpis' => [
                [
                    'label' => 'Solicitudes Nuevas',
                    'value' => $this->adminRepository->getNewLoanRequestsCount(),
                    'icon' => 'file-earmark-plus',
                    'color' => 'primary',
                ],
                [
                    'label' => 'Documentos Pendientes',
                    'value' => count($this->adminRepository->getPendingDocuments()),
                    'icon' => 'file-check',
                    'color' => 'warning',
                ],
                [
                    'label' => 'Mensajes sin Atender',
                    'value' => count($this->adminRepository->getOpenMessageThreads()),
                    'icon' => 'chat-left-text',
                    'color' => 'info',
                ],
                [
                    'label' => 'Usuarios sin Rol',
                    'value' => $this->adminRepository->getUnassignedUsersCount(),
                    'icon' => 'person-exclamation',
                    'color' => 'secondary',
                ],
            ],
            'loanKanban' => $this->adminRepository->getLoanKanbanData(),
            'pendingDocuments' => $this->adminRepository->getPendingDocuments(),
            'openMessages' => $this->adminRepository->getOpenMessageThreads(),
            'recentUsers' => $this->adminRepository->getRecentUsers(),
            'failedLogins' => $this->adminRepository->getRecentFailedLogins(),
            'dashboardTitle' => 'Dashboard del Administrador',
            'dashboardDescription' => 'Centro de operaciones – Solicitudes, documentos, comunicaciones y seguridad',
        ];
    }
}
