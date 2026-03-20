<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application\UseCase;

use App\Modules\Dashboard\Application\Service\AlertEvaluationService;
use App\Modules\Dashboard\Domain\Repository\MiPrestamoRepositoryInterface;
use App\Shared\Context\UserProviderInterface;

final readonly class GetAgremiadoDashboardDataUseCase
{
    public function __construct(
        private MiPrestamoRepositoryInterface $miPrestamoRepository,
        private AlertEvaluationService $alertService,
    ) {}

    public function execute(UserProviderInterface $userProvider): array
    {
        $user = $userProvider->get();
        if (!$user) {
            return [
                'alerts' => null,
                'hasActiveLoan' => false,
                'activeLoan' => null,
                'amortizationTable' => [],
                'userDocuments' => [],
                'pendingSignatures' => [],
                'recentPublications' => [],
                'dashboardTitle' => 'Mi Panel',
                'dashboardDescription' => 'Información de tu préstamo y gestiones personales',
            ];
        }

        $alerts = $this->alertService->evaluate($userProvider);
        $hasActiveLoan = $this->miPrestamoRepository->hasActiveLoan($user->id);
        $activeLoan = null;
        $amortizationTable = [];
        $pendingSignatures = [];

        if ($hasActiveLoan) {
            $activeLoan = $this->miPrestamoRepository->getActiveLoan($user->id);
            if ($activeLoan) {
                $amortizationTable = $this->miPrestamoRepository->getAmortizationTable($activeLoan['loan_id']);
            }
        }

        $pendingSignatures = $this->miPrestamoRepository->getPendingSignaturesForUser($user->id);

        return [
            'alerts' => $alerts,
            'hasActiveLoan' => $hasActiveLoan,
            'activeLoan' => $activeLoan,
            'amortizationTable' => $amortizationTable,
            'userDocuments' => $this->miPrestamoRepository->getUserDocuments($user->id),
            'pendingSignatures' => $pendingSignatures,
            'recentPublications' => $this->miPrestamoRepository->getRecentPublications(),
            'dashboardTitle' => 'Mi Panel',
            'dashboardDescription' => 'Información de tu préstamo y gestiones personales',
        ];
    }
}
