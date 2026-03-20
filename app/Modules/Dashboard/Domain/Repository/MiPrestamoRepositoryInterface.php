<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain\Repository;

/**
 * Personal loan and document data for Agremiado (member) dashboard.
 */
interface MiPrestamoRepositoryInterface
{
    /**
     * Check if user has active loans (status = 'desembolsado').
     */
    public function hasActiveLoan(int $userId): bool;

    /**
     * Get active loan for user (status = 'desembolsado'), or null.
     * Returns {loan_id, folio, original_amount, approved_amount, interest_rate,
     *          outstanding_balance, term_fortnights, first_payment_date, last_scheduled_payment_date,
     *          disburse_date}.
     */
    public function getActiveLoan(int $userId): ?array;

    /**
     * Full amortization table for a loan.
     * Returns array of {payment_number, scheduled_date, principal, interest, total, balance, status, days_overdue}.
     */
    public function getAmortizationTable(int $loanId): array;

    /**
     * User's documents (all types, all statuses).
     * Returns array of {document_id, document_type, status, observation, created_at, validated_by, validated_date}.
     */
    public function getUserDocuments(int $userId): array;

    /**
     * Legal documents pending user signature for their loan.
     * Returns array of {legal_doc_id, document_type, folio, requires_signature, signature_date}.
     */
    public function getPendingSignaturesForUser(int $userId): array;

    /**
     * Recent publications visible to agremiados (no expiration or expiration_date > TODAY).
     * Returns array of {publication_id, title, created_at, publication_type}.
     */
    public function getRecentPublications(int $limit = 4): array;
}
