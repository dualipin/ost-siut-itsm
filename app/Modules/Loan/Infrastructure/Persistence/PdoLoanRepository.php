<?php

namespace App\Modules\Loan\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Loan\Domain\Entity\Loan;
use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\ValueObject\Folio;
use App\Modules\Loan\Domain\ValueObject\InterestRate;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateTimeImmutable;

final class PdoLoanRepository extends PdoBaseRepository implements LoanRepositoryInterface
{
    public function findById(int $loanId): ?Loan
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loans WHERE loan_id = :id");
        $stmt->execute(['id' => $loanId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByFolio(Folio $folio): ?Loan
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loans WHERE folio = :folio");
        $stmt->execute(['folio' => $folio->toString()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loans WHERE user_id = :user_id ORDER BY application_date DESC");
        $stmt->execute(['user_id' => $userId]);
        
        return array_map([$this, 'hydrate'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByStatus(LoanStatusEnum $status): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loans WHERE status = :status ORDER BY application_date DESC");
        $stmt->execute(['status' => $status->value]);
        
        return array_map([$this, 'hydrate'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(Loan $loan): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO loans (
                user_id, folio, requested_amount, approved_amount, applied_interest_rate,
                daily_default_rate, estimated_total_to_pay, outstanding_balance,
                term_months, term_fortnights, first_payment_date, last_scheduled_payment_date,
                application_date, status, finance_signatory, lender_signatory, created_by
            ) VALUES (
                :user_id, :folio, :requested_amount, :approved_amount, :applied_interest_rate,
                :daily_default_rate, :estimated_total_to_pay, :outstanding_balance,
                :term_months, :term_fortnights, :first_payment_date, :last_scheduled_payment_date,
                :application_date, :status, :finance_signatory, :lender_signatory, :created_by
            )
        ");
        
        $stmt->execute([
            'user_id' => $loan->userId(),
            'folio' => $loan->folio()?->toString(),
            'requested_amount' => $loan->requestedAmount()->amount(),
            'approved_amount' => $loan->approvedAmount()?->amount(),
            'applied_interest_rate' => $loan->appliedInterestRate()->annual(),
            'daily_default_rate' => $loan->dailyDefaultRate(),
            'estimated_total_to_pay' => $loan->estimatedTotalToPay()?->amount(),
            'outstanding_balance' => $loan->outstandingBalance()->amount(),
            'term_months' => $loan->termMonths(),
            'term_fortnights' => $loan->termFortnights(),
            'first_payment_date' => $loan->firstPaymentDate()?->format('Y-m-d'),
            'last_scheduled_payment_date' => $loan->lastScheduledPaymentDate()?->format('Y-m-d'),
            'application_date' => $loan->applicationDate()->format('Y-m-d H:i:s'),
            'status' => $loan->status()->value,
            'finance_signatory' => $loan->financeSignatory(),
            'lender_signatory' => $loan->lenderSignatory(),
            'created_by' => $loan->createdBy()
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    public function update(Loan $loan): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE loans SET
                approved_amount = :approved_amount,
                applied_interest_rate = :applied_interest_rate,
                daily_default_rate = :daily_default_rate,
                estimated_total_to_pay = :estimated_total_to_pay,
                outstanding_balance = :outstanding_balance,
                term_fortnights = :term_fortnights,
                first_payment_date = :first_payment_date,
                last_scheduled_payment_date = :last_scheduled_payment_date,
                approval_date = :approval_date,
                disbursement_date = :disbursement_date,
                total_liquidation_date = :total_liquidation_date,
                status = :status,
                rejection_reason = :rejection_reason,
                finance_signatory = :finance_signatory,
                lender_signatory = :lender_signatory,
                requires_restructuring = :requires_restructuring
            WHERE loan_id = :loan_id
        ");
        
        $stmt->execute([
            'loan_id' => $loan->loanId(),
            'approved_amount' => $loan->approvedAmount()?->amount(),
            'applied_interest_rate' => $loan->appliedInterestRate()->annual(),
            'daily_default_rate' => $loan->dailyDefaultRate(),
            'estimated_total_to_pay' => $loan->estimatedTotalToPay()?->amount(),
            'outstanding_balance' => $loan->outstandingBalance()->amount(),
            'term_fortnights' => $loan->termFortnights(),
            'first_payment_date' => $loan->firstPaymentDate()?->format('Y-m-d'),
            'last_scheduled_payment_date' => $loan->lastScheduledPaymentDate()?->format('Y-m-d'),
            'approval_date' => $loan->approvalDate()?->format('Y-m-d H:i:s'),
            'disbursement_date' => $loan->disbursementDate()?->format('Y-m-d H:i:s'),
            'total_liquidation_date' => $loan->totalLiquidationDate()?->format('Y-m-d H:i:s'),
            'status' => $loan->status()->value,
            'rejection_reason' => $loan->rejectionReason(),
            'finance_signatory' => $loan->financeSignatory(),
            'lender_signatory' => $loan->lenderSignatory(),
            'requires_restructuring' => $loan->requiresRestructuring() ? 1 : 0
        ]);
    }

    public function getLastFolioForYear(int $year): ?Folio
    {
        $stmt = $this->pdo->prepare("
            SELECT folio FROM loans 
            WHERE folio LIKE :pattern 
            ORDER BY folio DESC 
            LIMIT 1
        ");
        $stmt->execute(['pattern' => "SIN-{$year}-%"]);
        $folioStr = $stmt->fetchColumn();
        
        return $folioStr ? Folio::parse($folioStr) : null;
    }

    public function countByUserAndStatus(int $userId, LoanStatusEnum $status): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM loans WHERE user_id = :user_id AND status = :status
        ");
        $stmt->execute(['user_id' => $userId, 'status' => $status->value]);
        
        return (int) $stmt->fetchColumn();
    }

    private function hydrate(array $row): Loan
    {
        return new Loan(
            loanId: (int) $row['loan_id'],
            userId: (int) $row['user_id'],
            folio: $row['folio'] ? Folio::parse($row['folio']) : null,
            requestedAmount: Money::fromFloat((float) $row['requested_amount']),
            approvedAmount: $row['approved_amount'] ? Money::fromFloat((float) $row['approved_amount']) : null,
            appliedInterestRate: InterestRate::fromPercentage((float) $row['applied_interest_rate']),
            dailyDefaultRate: $row['daily_default_rate'] ? (float) $row['daily_default_rate'] : null,
            estimatedTotalToPay: $row['estimated_total_to_pay'] ? Money::fromFloat((float) $row['estimated_total_to_pay']) : null,
            outstandingBalance: Money::fromFloat((float) $row['outstanding_balance']),
            termMonths: $row['term_months'] ? (int) $row['term_months'] : null,
            termFortnights: $row['term_fortnights'] ? (int) $row['term_fortnights'] : null,
            firstPaymentDate: $row['first_payment_date'] ? new DateTimeImmutable($row['first_payment_date']) : null,
            lastScheduledPaymentDate: $row['last_scheduled_payment_date'] ? new DateTimeImmutable($row['last_scheduled_payment_date']) : null,
            applicationDate: new DateTimeImmutable($row['application_date']),
            documentReviewDate: $row['document_review_date'] ? new DateTimeImmutable($row['document_review_date']) : null,
            approvalDate: $row['approval_date'] ? new DateTimeImmutable($row['approval_date']) : null,
            documentGenerationDate: $row['document_generation_date'] ? new DateTimeImmutable($row['document_generation_date']) : null,
            signatureValidationDate: $row['signature_validation_date'] ? new DateTimeImmutable($row['signature_validation_date']) : null,
            disbursementDate: $row['disbursement_date'] ? new DateTimeImmutable($row['disbursement_date']) : null,
            totalLiquidationDate: $row['total_liquidation_date'] ? new DateTimeImmutable($row['total_liquidation_date']) : null,
            status: LoanStatusEnum::from($row['status']),
            originalLoanId: $row['original_loan_id'] ? (int) $row['original_loan_id'] : null,
            rejectionReason: $row['rejection_reason'],
            adminObservations: $row['admin_observations'],
            internalObservations: $row['internal_observations'],
            financeSignatory: $row['finance_signatory'],
            lenderSignatory: $row['lender_signatory'],
            requiresRestructuring: (bool) $row['requires_restructuring'],
            createdBy: $row['created_by'] ? (int) $row['created_by'] : null,
            deletionDate: $row['deletion_date'] ? new DateTimeImmutable($row['deletion_date']) : null
        );
    }
}
