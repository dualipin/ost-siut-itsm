<?php

namespace App\Modules\Loan\Domain\Entity;

use App\Modules\Loan\Domain\Enum\ReceiptTypeEnum;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateTimeImmutable;

final class Receipt
{
    public function __construct(
        private ?int $receiptId,
        private int $loanId,
        private ?int $amortizationId,
        private ReceiptTypeEnum $receiptType,
        private string $receiptFolio,
        private Money $amount,
        private ?string $description,
        private DateTimeImmutable $issueDate,
        private ?string $pdfPath
    ) {}

    // Getters
    public function receiptId(): ?int
    {
        return $this->receiptId;
    }

    public function loanId(): int
    {
        return $this->loanId;
    }

    public function amortizationId(): ?int
    {
        return $this->amortizationId;
    }

    public function receiptType(): ReceiptTypeEnum
    {
        return $this->receiptType;
    }

    public function receiptFolio(): string
    {
        return $this->receiptFolio;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function issueDate(): DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function pdfPath(): ?string
    {
        return $this->pdfPath;
    }
}
