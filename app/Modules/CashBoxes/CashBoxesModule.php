<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes;

use App\Modules\AbstractModule;
use App\Modules\CashBoxes\Application\UseCase\CloseCashBoxUseCase;
use App\Modules\CashBoxes\Application\UseCase\CreateAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Application\UseCase\CreateCashBoxUseCase;
use App\Modules\CashBoxes\Application\UseCase\CreateCategoryUseCase;
use App\Modules\CashBoxes\Application\UseCase\DeleteAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Application\UseCase\BuildFiscalReportUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetFinancialReportsListUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetCashBoxDetailUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetCashBoxesListUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetCategoriesListUseCase;
use App\Modules\CashBoxes\Application\UseCase\ListAnnualFinancialReportsUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetTransactionsViewDataUseCase;
use App\Modules\CashBoxes\Application\UseCase\RecordTransactionUseCase;
use App\Modules\CashBoxes\Application\UseCase\SaveFinancialReportUseCase;
use App\Modules\CashBoxes\Application\UseCase\TransferFundsUseCase;
use App\Modules\CashBoxes\Application\UseCase\UpdateAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Application\UseCase\UpdateCategoryUseCase;
use App\Modules\CashBoxes\Domain\Repository\AnnualFinancialReportRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\CategoryRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\FinancialReportRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\TransactionRepositoryInterface;
use App\Modules\CashBoxes\Infrastructure\Persistence\PdoAnnualFinancialReportRepository;
use App\Modules\CashBoxes\Infrastructure\Persistence\PdoCashBoxRepository;
use App\Modules\CashBoxes\Infrastructure\Persistence\PdoCategoryRepository;
use App\Modules\CashBoxes\Infrastructure\Persistence\PdoFinancialReportRepository;
use App\Modules\CashBoxes\Infrastructure\Persistence\PdoTransactionRepository;

final class CashBoxesModule extends AbstractModule
{
    protected const array REPOSITORIES = [
        AnnualFinancialReportRepositoryInterface::class => PdoAnnualFinancialReportRepository::class,
        CashBoxRepositoryInterface::class => PdoCashBoxRepository::class,
        TransactionRepositoryInterface::class => PdoTransactionRepository::class,
        CategoryRepositoryInterface::class => PdoCategoryRepository::class,
        FinancialReportRepositoryInterface::class => PdoFinancialReportRepository::class,
    ];

    protected const array USE_CASES = [
        BuildFiscalReportUseCase::class,
        CloseCashBoxUseCase::class,
        CreateAnnualFinancialReportUseCase::class,
        CreateCashBoxUseCase::class,
        CreateCategoryUseCase::class,
        DeleteAnnualFinancialReportUseCase::class,
        GetCashBoxDetailUseCase::class,
        GetCashBoxesListUseCase::class,
        GetAnnualFinancialReportUseCase::class,
        GetCategoriesListUseCase::class,
        GetFinancialReportsListUseCase::class,
        ListAnnualFinancialReportsUseCase::class,
        GetTransactionsViewDataUseCase::class,
        RecordTransactionUseCase::class,
        SaveFinancialReportUseCase::class,
        TransferFundsUseCase::class,
        UpdateAnnualFinancialReportUseCase::class,
        UpdateCategoryUseCase::class,
    ];
}
