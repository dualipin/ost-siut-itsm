<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Dashboard\Domain\Repository\KpiLiderRepositoryInterface;
use DateTimeImmutable;

final class PdoKpiLiderRepository extends PdoBaseRepository implements KpiLiderRepositoryInterface
{
    public function getTotalActiveMembersCount(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) as count FROM users WHERE role = 'agremiado' AND active = 1 AND delete_at IS NULL"
        );
        return (int)($stmt->fetch()['count'] ?? 0);
    }

    public function getCarteTotalBalance(): float
    {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(outstanding_balance), 0) as total FROM loans WHERE status = 'desembolsado' AND deletion_date IS NULL"
        );
        return (float)($stmt->fetch()['total'] ?? 0);
    }

    public function getMonthlyRecoveryRate(): float
    {
        // Current month: payments made / payments scheduled * 100
        $stmt = $this->pdo->query(
            "SELECT
                SUM(CASE WHEN la.payment_status = 'pagado' THEN la.total_scheduled_payment ELSE 0 END) as paid,
                SUM(la.total_scheduled_payment) as scheduled
            FROM loan_amortization la
            WHERE MONTH(la.scheduled_date) = MONTH(CURDATE())
            AND YEAR(la.scheduled_date) = YEAR(CURDATE())"
        );

        $row = $stmt->fetch();
        $paid = (float)($row['paid'] ?? 0);
        $scheduled = (float)($row['scheduled'] ?? 0);

        return $scheduled > 0 ? ($paid / $scheduled) * 100 : 0;
    }

    public function getLoansInDefaultCount(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(DISTINCT loan_id) as count FROM loan_amortization WHERE days_overdue > 0 AND active = 1"
        );
        return (int)($stmt->fetch()['count'] ?? 0);
    }

    public function getPortfolioEvolution(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                DATE_FORMAT(l.disbursement_date, '%Y-%m') as month,
                SUM(CASE WHEN l.status = 'desembolsado' THEN l.approved_amount ELSE 0 END) as disbursed,
                SUM(CASE WHEN la.payment_status = 'pagado' THEN la.total_scheduled_payment ELSE 0 END) as recovered
            FROM loans l
            LEFT JOIN loan_amortization la ON l.loan_id = la.loan_id AND la.payment_status = 'pagado'
            WHERE l.disbursement_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            AND l.deletion_date IS NULL
            GROUP BY DATE_FORMAT(l.disbursement_date, '%Y-%m')
            ORDER BY month ASC"
        );

        return $stmt->fetchAll();
    }

    public function getLoansByStatus(): array
    {
        $stmt = $this->pdo->query(
            "SELECT status, COUNT(*) as count FROM loans WHERE deletion_date IS NULL GROUP BY status ORDER BY status"
        );
        return $stmt->fetchAll();
    }

    public function getTop5LoansHighestBalance(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                l.folio,
                CONCAT(u.name, ' ', u.surnames) as name,
                l.approved_amount as original_amount,
                l.outstanding_balance as outstanding_balance
            FROM loans l
            JOIN users u ON l.user_id = u.user_id
            WHERE l.status = 'desembolsado'
            AND l.deletion_date IS NULL
            ORDER BY l.outstanding_balance DESC
            LIMIT 5"
        );
        return $stmt->fetchAll();
    }

    public function getNewUsersLast30DaysByRole(): array
    {
        $stmt = $this->pdo->query(
            "SELECT role, COUNT(*) as count FROM users
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND delete_at IS NULL
            GROUP BY role
            ORDER BY role"
        );
        return $stmt->fetchAll();
    }

    public function getRecentPublications(int $limit = 3): array
    {
        $limit = (int)$limit;
        $stmt = $this->pdo->query(
            "SELECT publication_id, title, created_at, expiration_date
            FROM publications
            ORDER BY created_at DESC
            LIMIT " . $limit
        );
        return $stmt->fetchAll();
    }
}
