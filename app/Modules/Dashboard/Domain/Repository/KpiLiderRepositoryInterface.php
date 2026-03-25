<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain\Repository;

/**
 * KPI and metrics data for Líder dashboard (read-only, strategic view).
 */
interface KpiLiderRepositoryInterface
{
    /**
     * Total active members (users with role = 'agremiado' and active = TRUE).
     */
    public function getTotalActiveMembersCount(): int;

    /**
     * Total portfolio balance (sum of outstanding_balance from all desembolsado loans).
     */
    public function getCarteTotalBalance(): float;

    /**
     * Recovery rate for the current month (paid amount / scheduled amount * 100).
     */
    public function getMonthlyRecoveryRate(): float;

    /**
     * Count of unique loans with active overdue payments (days_overdue > 0).
     */
    public function getLoansInDefaultCount(): int;

    /**
     * Portfolio evolution data (last 12 months).
     * Returns array of {month: string, disbursed: float, recovered: float}.
     */
    public function getPortfolioEvolution(): array;

    /**
     * Loan status distribution (borrador, revisión, aprobado, desembolsado, liquidado, rechazado).
     * Returns array of {status: string, count: int}.
     */
    public function getLoansByStatus(): array;

    /**
     * Top 5 loans by outstanding balance.
     * Returns array of {folio: string, name: string, original_amount: float, outstanding_balance: float}.
     */
    public function getTop5LoansHighestBalance(): array;

    /**
     * New user registrations in the last 30 days, grouped by role.
     * Returns array of {role: string, count: int}.
     */
    public function getNewUsersLast30DaysByRole(): array;

    /**
     * Recent publications (last 3), ordered by creation date DESC.
     * Returns array of {publication_id: int, title: string, publication_type: string, created_at: string, expiration_date: ?string}.
     */
    public function getRecentPublications(int $limit = 3): array;
}
