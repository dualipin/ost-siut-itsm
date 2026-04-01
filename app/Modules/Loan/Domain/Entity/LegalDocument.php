<?php

namespace App\Modules\Loan\Domain\Entity;

use App\Modules\Loan\Domain\Enum\DocumentTypeEnum;
use DateTimeImmutable;

final class LegalDocument
{
    public function __construct(
        private ?int $legalDocId,
        private int $loanId,
        private DocumentTypeEnum $documentType,
        private string $filePath,
        private int $version,
        private bool $requiresUserSignature,
        private ?string $userSignatureUrl,
        private ?DateTimeImmutable $userSignatureDate,
        private bool $requiresFinanceValidation,
        private bool $validatedByFinance,
        private ?int $validatedBy,
        private ?DateTimeImmutable $validationDate,
        private ?string $validationObservations,
        private DateTimeImmutable $generationDate,
        private ?int $generatedBy
    ) {}

    // Getters
    public function legalDocId(): ?int
    {
        return $this->legalDocId;
    }

    public function loanId(): int
    {
        return $this->loanId;
    }

    public function documentType(): DocumentTypeEnum
    {
        return $this->documentType;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function requiresUserSignature(): bool
    {
        return $this->requiresUserSignature;
    }

    public function userSignatureUrl(): ?string
    {
        return $this->userSignatureUrl;
    }

    public function userSignatureDate(): ?DateTimeImmutable
    {
        return $this->userSignatureDate;
    }

    public function requiresFinanceValidation(): bool
    {
        return $this->requiresFinanceValidation;
    }

    public function isValidatedByFinance(): bool
    {
        return $this->validatedByFinance;
    }

    public function validatedBy(): ?int
    {
        return $this->validatedBy;
    }

    public function validationDate(): ?DateTimeImmutable
    {
        return $this->validationDate;
    }

    public function validationObservations(): ?string
    {
        return $this->validationObservations;
    }

    public function generationDate(): DateTimeImmutable
    {
        return $this->generationDate;
    }

    public function generatedBy(): ?int
    {
        return $this->generatedBy;
    }

    public function isSignedByUser(): bool
    {
        return $this->userSignatureUrl !== null && $this->userSignatureDate !== null;
    }

    public function isFullyValidated(): bool
    {
        return (!$this->requiresUserSignature || $this->isSignedByUser()) &&
               (!$this->requiresFinanceValidation || $this->validatedByFinance);
    }
}
