<?php

namespace App\Modules\Loan\Domain\Entity;

use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\ValueObject\Folio;
use App\Modules\Loan\Domain\ValueObject\InterestRate;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateTimeImmutable;

final class Loan
{
    public function __construct(
        private ?int $loanId,
        private int $userId,
        private ?Folio $folio,
        private Money $requestedAmount,
        private ?Money $approvedAmount,
        private InterestRate $appliedInterestRate,
        private ?float $dailyDefaultRate,
        private ?Money $estimatedTotalToPay,
        private Money $outstandingBalance,
        private ?int $termMonths,
        private ?int $termFortnights,
        private ?DateTimeImmutable $firstPaymentDate,
        private ?DateTimeImmutable $lastScheduledPaymentDate,
        private DateTimeImmutable $applicationDate,
        private ?DateTimeImmutable $documentReviewDate,
        private ?DateTimeImmutable $approvalDate,
        private ?DateTimeImmutable $documentGenerationDate,
        private ?DateTimeImmutable $signatureValidationDate,
        private ?DateTimeImmutable $disbursementDate,
        private ?DateTimeImmutable $totalLiquidationDate,
        private LoanStatusEnum $status,
        private ?int $originalLoanId,
        private ?string $rejectionReason,
        private ?string $adminObservations,
        private ?string $internalObservations,
        private ?string $financeSignatory,
        private ?string $lenderSignatory,
        private bool $requiresRestructuring,
        private ?int $createdBy,
        private ?DateTimeImmutable $deletionDate
    ) {}

    // Getters
    public function loanId(): ?int
    {
        return $this->loanId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function folio(): ?Folio
    {
        return $this->folio;
    }

    public function requestedAmount(): Money
    {
        return $this->requestedAmount;
    }

    public function approvedAmount(): ?Money
    {
        return $this->approvedAmount;
    }

    public function appliedInterestRate(): InterestRate
    {
        return $this->appliedInterestRate;
    }

    public function dailyDefaultRate(): ?float
    {
        return $this->dailyDefaultRate;
    }

    public function estimatedTotalToPay(): ?Money
    {
        return $this->estimatedTotalToPay;
    }

    public function outstandingBalance(): Money
    {
        return $this->outstandingBalance;
    }

    public function termMonths(): ?int
    {
        return $this->termMonths;
    }

    public function termFortnights(): ?int
    {
        return $this->termFortnights;
    }

    public function firstPaymentDate(): ?DateTimeImmutable
    {
        return $this->firstPaymentDate;
    }

    public function lastScheduledPaymentDate(): ?DateTimeImmutable
    {
        return $this->lastScheduledPaymentDate;
    }

    public function applicationDate(): DateTimeImmutable
    {
        return $this->applicationDate;
    }

    public function documentReviewDate(): ?DateTimeImmutable
    {
        return $this->documentReviewDate;
    }

    public function approvalDate(): ?DateTimeImmutable
    {
        return $this->approvalDate;
    }

    public function documentGenerationDate(): ?DateTimeImmutable
    {
        return $this->documentGenerationDate;
    }

    public function signatureValidationDate(): ?DateTimeImmutable
    {
        return $this->signatureValidationDate;
    }

    public function disbursementDate(): ?DateTimeImmutable
    {
        return $this->disbursementDate;
    }

    public function totalLiquidationDate(): ?DateTimeImmutable
    {
        return $this->totalLiquidationDate;
    }

    public function status(): LoanStatusEnum
    {
        return $this->status;
    }

    public function originalLoanId(): ?int
    {
        return $this->originalLoanId;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function adminObservations(): ?string
    {
        return $this->adminObservations;
    }

    public function internalObservations(): ?string
    {
        return $this->internalObservations;
    }

    public function financeSignatory(): ?string
    {
        return $this->financeSignatory;
    }

    public function lenderSignatory(): ?string
    {
        return $this->lenderSignatory;
    }

    public function requiresRestructuring(): bool
    {
        return $this->requiresRestructuring;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function deletionDate(): ?DateTimeImmutable
    {
        return $this->deletionDate;
    }

    // State checkers
    public function isDraft(): bool
    {
        return $this->status === LoanStatusEnum::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === LoanStatusEnum::Submitted;
    }

    public function isApproved(): bool
    {
        return $this->status === LoanStatusEnum::Approved;
    }

    public function isActive(): bool
    {
        return $this->status === LoanStatusEnum::Active;
    }

    public function isLiquidated(): bool
    {
        return $this->status === LoanStatusEnum::Liquidated;
    }

    public function isRestructured(): bool
    {
        return $this->status === LoanStatusEnum::Restructured;
    }
}
