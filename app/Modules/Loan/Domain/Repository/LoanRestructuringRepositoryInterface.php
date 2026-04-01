<?php

declare(strict_types=1);

namespace App\Modules\Loan\Domain\Repository;

/**
 * Repository interface for Loan Restructurings
 * 
 * Table: loan_restructurings
 * Columns (13): id, original_loan_id, new_loan_id, restructure_date, 
 *               original_balance, new_balance, reason, approved_by, 
 *               created_at, updated_at, notes, ...
 */
interface LoanRestructuringRepositoryInterface
{
    /**
     * Create a new restructuring record
     * @param array{
     *   original_loan_id: int,
     *   new_loan_id: int,
     *   restructure_date: string,
     *   original_balance: float,
     *   new_balance: float,
     *   reason: string,
     *   approved_by: int,
     *   notes?: string
     * } $data
     */
    public function create(array $data): int;

    /**
     * Find restructurings by original loan ID
     * @return array<object>
     */
    public function findByOriginalLoanId(int $loanId): array;

    /**
     * Find restructuring by new loan ID
     */
    public function findByNewLoanId(int $loanId): ?object;
}
