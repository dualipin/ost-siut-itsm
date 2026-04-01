<?php

namespace App\Modules\Loan;

use App\Modules\AbstractModule;
use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Application\Service\ElectronicSignatureService;
use App\Modules\Loan\Application\Service\FolioGenerator;
use App\Modules\Loan\Application\Service\LoanEventLogger;
use App\Modules\Loan\Application\Service\PdfGeneratorInterface;
use App\Modules\Loan\Application\UseCase\GenerateAccountStatementUseCase;
use App\Modules\Loan\Application\UseCase\RegisterExtraordinaryPaymentUseCase;
use App\Modules\Loan\Application\UseCase\RegisterPaymentUseCase;
use App\Modules\Loan\Application\UseCase\RegisterPicoUseCase;
use App\Modules\Loan\Application\UseCase\RestructureLoanUseCase;
use App\Modules\Loan\Application\UseCase\ReviewLoanApplicationUseCase;
use App\Modules\Loan\Application\UseCase\SubmitLoanApplicationUseCase;
use App\Modules\Loan\Application\UseCase\ValidateSignedDocumentsUseCase;
use App\Modules\Loan\Domain\Repository\AmortizationRepositoryInterface;
use App\Modules\Loan\Domain\Repository\ExtraordinaryPaymentRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LegalDocRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LoanRestructuringRepositoryInterface;
use App\Modules\Loan\Domain\Repository\PaymentConfigRepositoryInterface;
use App\Modules\Loan\Domain\Repository\ReceiptRepositoryInterface;
use App\Modules\Loan\Domain\Repository\SaverUserRepositoryInterface;
use App\Modules\Loan\Domain\Service\InterestRateProvider;
use App\Modules\Loan\Infrastructure\Persistence\PdoAmortizationRepository;
use App\Modules\Loan\Infrastructure\Persistence\PdoExtraordinaryPaymentRepository;
use App\Modules\Loan\Infrastructure\Persistence\PdoLegalDocRepository;
use App\Modules\Loan\Infrastructure\Persistence\PdoLoanRepository;
use App\Modules\Loan\Infrastructure\Persistence\PdoLoanRestructuringRepository;
use App\Modules\Loan\Infrastructure\Persistence\PdoPaymentConfigRepository;
use App\Modules\Loan\Infrastructure\Persistence\PdoReceiptRepository;
use App\Modules\Loan\Infrastructure\Persistence\PdoSaverUserRepository;

final class LoanModule extends AbstractModule
{
    protected const array REPOSITORIES = [
        LoanRepositoryInterface::class => PdoLoanRepository::class,
        AmortizationRepositoryInterface::class => PdoAmortizationRepository::class,
        PaymentConfigRepositoryInterface::class => PdoPaymentConfigRepository::class,
        LegalDocRepositoryInterface::class => PdoLegalDocRepository::class,
        ReceiptRepositoryInterface::class => PdoReceiptRepository::class,
        SaverUserRepositoryInterface::class => PdoSaverUserRepository::class,
        ExtraordinaryPaymentRepositoryInterface::class => PdoExtraordinaryPaymentRepository::class,
        LoanRestructuringRepositoryInterface::class => PdoLoanRestructuringRepository::class,
    ];

    protected const array SERVICES = [
        InterestRateProvider::class,
        AmortizationCalculator::class,
        FolioGenerator::class,
        ElectronicSignatureService::class,
        LoanEventLogger::class,
        // PdfGeneratorInterface implementation will be added in Phase 7
    ];

    protected const array USE_CASES = [
        SubmitLoanApplicationUseCase::class,
        ReviewLoanApplicationUseCase::class,
        ValidateSignedDocumentsUseCase::class,
        RegisterPaymentUseCase::class,
        RegisterPicoUseCase::class,
        RegisterExtraordinaryPaymentUseCase::class,
        RestructureLoanUseCase::class,
        GenerateAccountStatementUseCase::class,
    ];
}
