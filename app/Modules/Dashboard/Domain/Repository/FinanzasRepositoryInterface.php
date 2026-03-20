<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain\Repository;

/**
 * Financial tracking and payment data for Finanzas dashboard.
 */
interface FinanzasRepositoryInterface
{
    /**
     * Payments scheduled for a specific date.
     * Returns array of {amortization_id, folio, user_name, payment_number, principal, interest, total, status}.
     */
    public function getScheduledPaymentsForDate(\DateTimeImmutable $date): array;

    /**
     * Total amount scheduled for payment on a specific date.
     */
    public function getTotalScheduledForDate(\DateTimeImmutable $date): float;

    /**
     * Overdue payments (scheduled_date < TODAY and payment_status = 'pendiente').
     * Returns array of {amortization_id, folio, user_name, payment_number, days_overdue, total_payment, generated_default_interest}.
     */
    public function getOverduePayments(): array;

    /**
     * Scheduled payments for the next 15 days (quincena).
     * Returns array of {date: string, count: int, total: float}.
     */
    public function getPaymentsNext15Days(): array;

    /**
     * Loans currently in default (days_overdue > 0), unique per loan.
     * Returns array of {loan_id, folio, user_name, oldest_overdue_days, default_interest_accumulated}.
     */
    public function getLoansInDefault(): array;

    /**
     * Legal documents pending finance validation (requires_finance_validation = TRUE AND validated_by_finance = FALSE).
     * Returns array of {legal_doc_id, folio, document_type, loan_id, generation_date, days_waiting}.
     */
    public function getPendingLegalDocuments(): array;

    /**
     * Legal documents pending user signature (requires_user_signature = TRUE AND user_signature_date IS NULL).
     * Returns array of {legal_doc_id, folio, document_type, loan_id, days_waiting}.
     */
    public function getPendingUserSignatures(): array;

    /**
     * Active loan restructurings (new_loan_id with status = 'borrador' or 'revisión').
     * Returns array of {restructuring_id, original_folio, new_amount, new_rate, new_fortnights, restructuring_date}.
     */
    public function getActiveRestructurings(): array;

    /**
     * Mail queue status (pending/failed count).
     * Returns {pending: int, failed: int}.
     */
    public function getMailQueueStatus(): array;
}
