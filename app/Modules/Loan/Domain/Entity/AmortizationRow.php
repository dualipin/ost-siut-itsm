<?php

namespace App\Modules\Loan\Domain\Entity;

use App\Modules\Loan\Domain\Enum\PaymentStatusEnum;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateTimeImmutable;

final class AmortizationRow
{
    public function __construct(
        private ?int $amortizationId,
        private int $loanId,
        private int $paymentNumber,
        private int $incomeTypeId,
        private DateTimeImmutable $scheduledDate,
        private Money $initialBalance,
        private Money $principal,
        private Money $ordinaryInterest,
        private Money $totalScheduledPayment,
        private Money $finalBalance,
        private PaymentStatusEnum $paymentStatus,
        private ?DateTimeImmutable $actualPaymentDate,
        private Money $actualPaidAmount,
        private int $daysOverdue,
        private Money $generatedDefaultInterest,
        private ?int $paidBy,
        private ?string $paymentReceipt,
        private int $tableVersion,
        private bool $active
    ) {}

    // Getters
    public function amortizationId(): ?int
    {
        return $this->amortizationId;
    }

    public function loanId(): int
    {
        return $this->loanId;
    }

    public function paymentNumber(): int
    {
        return $this->paymentNumber;
    }

    public function incomeTypeId(): int
    {
        return $this->incomeTypeId;
    }

    public function scheduledDate(): DateTimeImmutable
    {
        return $this->scheduledDate;
    }

    public function initialBalance(): Money
    {
        return $this->initialBalance;
    }

    public function principal(): Money
    {
        return $this->principal;
    }

    public function ordinaryInterest(): Money
    {
        return $this->ordinaryInterest;
    }

    public function totalScheduledPayment(): Money
    {
        return $this->totalScheduledPayment;
    }

    public function finalBalance(): Money
    {
        return $this->finalBalance;
    }

    public function paymentStatus(): PaymentStatusEnum
    {
        return $this->paymentStatus;
    }

    public function actualPaymentDate(): ?DateTimeImmutable
    {
        return $this->actualPaymentDate;
    }

    public function actualPaidAmount(): Money
    {
        return $this->actualPaidAmount;
    }

    public function daysOverdue(): int
    {
        return $this->daysOverdue;
    }

    public function generatedDefaultInterest(): Money
    {
        return $this->generatedDefaultInterest;
    }

    public function paidBy(): ?int
    {
        return $this->paidBy;
    }

    public function paymentReceipt(): ?string
    {
        return $this->paymentReceipt;
    }

    public function tableVersion(): int
    {
        return $this->tableVersion;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isPending(): bool
    {
        return $this->paymentStatus === PaymentStatusEnum::Pending;
    }

    public function isPaid(): bool
    {
        return $this->paymentStatus === PaymentStatusEnum::Paid;
    }

    public function isPico(): bool
    {
        return $this->paymentStatus === PaymentStatusEnum::Pico;
    }
}
