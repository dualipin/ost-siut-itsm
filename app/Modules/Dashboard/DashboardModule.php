<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use App\Modules\AbstractModule;
use App\Modules\Dashboard\Application\Service\AlertEvaluationService;
use App\Modules\Dashboard\Application\UseCase\GetAdministradorDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetAgremiadoDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetFinanzasDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetLiderDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetPublicDashboardDataUseCase;
use App\Modules\Dashboard\Domain\Repository\DashboardAdministradorRepositoryInterface;
use App\Modules\Dashboard\Domain\Repository\FinanzasRepositoryInterface;
use App\Modules\Dashboard\Domain\Repository\KpiLiderRepositoryInterface;
use App\Modules\Dashboard\Domain\Repository\MiPrestamoRepositoryInterface;
use App\Modules\Dashboard\Domain\Repository\PublicDashboardRepositoryInterface;
use App\Modules\Dashboard\Infrastructure\Persistence\AdminFailedMailQueueAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\AdminPendingDocumentsAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\AdminUnattendedMessagesAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\AgremiadoPendingSignaturesAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\FinanzasOverduePaymentsAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\FinanzasPendingDocumentsAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\LiderInactiveRegistrationsAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\LiderOverdueLoansAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\PdoDashboardAdministradorRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\PdoFinanzasRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\PdoKpiLiderRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\PdoMiPrestamoRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\PdoPublicDashboardRepository;

final class DashboardModule extends AbstractModule
{
    protected const array REPOSITORIES = [
        KpiLiderRepositoryInterface::class => PdoKpiLiderRepository::class,
        DashboardAdministradorRepositoryInterface::class => PdoDashboardAdministradorRepository::class,
        FinanzasRepositoryInterface::class => PdoFinanzasRepository::class,
        MiPrestamoRepositoryInterface::class => PdoMiPrestamoRepository::class,
        PublicDashboardRepositoryInterface::class => PdoPublicDashboardRepository::class,
    ];

    protected const array SERVICES = [
        AlertEvaluationService::class,
        LiderOverdueLoansAlertEvaluator::class,
        LiderInactiveRegistrationsAlertEvaluator::class,
        AdminPendingDocumentsAlertEvaluator::class,
        AdminUnattendedMessagesAlertEvaluator::class,
        AdminFailedMailQueueAlertEvaluator::class,
        FinanzasOverduePaymentsAlertEvaluator::class,
        FinanzasPendingDocumentsAlertEvaluator::class,
        AgremiadoPendingSignaturesAlertEvaluator::class,
    ];

    protected const array USE_CASES = [
        GetLiderDashboardDataUseCase::class,
        GetAdministradorDashboardDataUseCase::class,
        GetFinanzasDashboardDataUseCase::class,
        GetAgremiadoDashboardDataUseCase::class,
        GetPublicDashboardDataUseCase::class,
    ];
}
