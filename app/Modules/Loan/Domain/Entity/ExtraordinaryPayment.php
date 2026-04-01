<?php

namespace App\Modules\Loan\Domain\Entity;

use App\Modules\Loan\Domain\Enum\PaymentTypeEnum;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateTimeImmutable;

final class ExtraordinaryPayment
{
    public function __construct(
        private ?int $extraordinaryPaymentId,
        private int $loanId,
        private PaymentTypeEnum $paymentType,
        private Money $amount,
        private DateTimeImmutable $paymentDate,
        private ?Money $appliedToPrincipal,
        private ?Money $appliedToInterest,
        private ?Money $appliedToDefault,
        private bool $regeneratedAmortizationTable,
        private ?int $generatedTableVersion,
        private ?string $observations,
        private ?string $paymentReceipt,
        private ?int $registeredBy
    ) {}

    // Getters
    public function extraordinaryPaymentId(): ?int
    {
        return $this->extraordinaryPaymentId;
    }

    public function loanId(): int
    {
        return $this->loanId;
    }

    public function paymentType(): PaymentTypeEnum
    {
        return $this->paymentType;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function paymentDate(): DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function appliedToPrincipal(): ?Money
    {
        return $this->appliedToPrincipal;
    }

    public function appliedToInterest(): ?Money
    {
        return $this->appliedToInterest;
    }

    public function appliedToDefault(): ?Money
    {
        return $this->appliedToDefault;
    }

    public function regeneratedAmortizationTable(): bool
    {
        return $this->regeneratedAmortizationTable;
    }

    public function generatedTableVersion(): ?int
    {
        return $this->generatedTableVersion;
    }

    public function observations(): ?string
    {
        return $this->observations;
    }

    public function paymentReceipt(): ?string
    {
        return $this->paymentReceipt;
    }

    public function registeredBy(): ?int
    {
        return $this->registeredBy;
    }

    public function isTotalLiquidation(): bool
    {
        return $this->paymentType === PaymentTypeEnum::TotalLiquidation;
    }
}
