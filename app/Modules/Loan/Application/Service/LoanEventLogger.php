<?php

namespace App\Modules\Loan\Application\Service;

use DateTimeImmutable;
use PDO;

final readonly class LoanEventLogger
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function logLoanCreated(int $loanId, int $userId): void
    {
        $this->log($loanId, 'loan_created', "Loan created by user {$userId}");
    }

    public function logLoanSubmitted(int $loanId): void
    {
        $this->log($loanId, 'loan_submitted', "Loan application submitted");
    }

    public function logLoanApproved(int $loanId, int $approvedBy, float $approvedAmount): void
    {
        $this->log($loanId, 'loan_approved', "Loan approved for \${$approvedAmount} by user {$approvedBy}");
    }

    public function logLoanRejected(int $loanId, int $rejectedBy, string $reason): void
    {
        $this->log($loanId, 'loan_rejected', "Loan rejected by user {$rejectedBy}: {$reason}");
    }

    public function logDocumentsGenerated(int $loanId): void
    {
        $this->log($loanId, 'documents_generated', "Legal documents generated");
    }

    public function logDocumentsValidated(int $loanId, int $validatedBy): void
    {
        $this->log($loanId, 'documents_validated', "Documents validated by user {$validatedBy}");
    }

    public function logLoanActivated(int $loanId): void
    {
        $this->log($loanId, 'loan_activated', "Loan activated and funds disbursed");
    }

    public function logPaymentRegistered(int $loanId, int $amortizationId, float $amount): void
    {
        $this->log($loanId, 'payment_registered', "Payment of \${$amount} registered for amortization {$amortizationId}");
    }

    public function logPicoRegistered(int $loanId, int $amortizationId, int $daysOverdue): void
    {
        $this->log($loanId, 'pico_registered', "Pico registered for amortization {$amortizationId} ({$daysOverdue} days overdue)");
    }

    public function logExtraordinaryPayment(int $loanId, string $type, float $amount): void
    {
        $this->log($loanId, 'extraordinary_payment', "Extraordinary payment ({$type}): \${$amount}");
    }

    public function logTableRegenerated(int $loanId, int $newVersion): void
    {
        $this->log($loanId, 'table_regenerated', "Amortization table regenerated (version {$newVersion})");
    }

    public function logLoanLiquidated(int $loanId): void
    {
        $this->log($loanId, 'loan_liquidated', "Loan fully liquidated");
    }

    public function logLoanRestructured(int $originalLoanId, int $newLoanId): void
    {
        $this->log($originalLoanId, 'loan_restructured', "Loan restructured into new loan {$newLoanId}");
    }

    private function log(int $loanId, string $eventType, string $description): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO loan_events (loan_id, event_type, description, event_date)
            VALUES (:loan_id, :event_type, :description, :event_date)
        ");
        
        $stmt->execute([
            'loan_id' => $loanId,
            'event_type' => $eventType,
            'description' => $description,
            'event_date' => (new DateTimeImmutable())->format('Y-m-d H:i:s')
        ]);
    }
}
