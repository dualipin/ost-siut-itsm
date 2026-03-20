<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Dashboard\Domain\Repository\DashboardAdministradorRepositoryInterface;

final class PdoDashboardAdministradorRepository extends PdoBaseRepository implements DashboardAdministradorRepositoryInterface
{
    public function getNewLoanRequestsCount(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) as count FROM loans WHERE status = 'borrador' AND deletion_date IS NULL"
        );
        return (int)($stmt->fetch()['count'] ?? 0);
    }

    public function getPendingDocuments(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                ud.document_id,
                ud.user_id,
                CONCAT(u.name, ' ', u.surnames) as user_name,
                ud.document_type,
                ud.created_at,
                DATEDIFF(CURDATE(), DATE(ud.created_at)) as days_waiting
            FROM user_documents ud
            JOIN users u ON ud.user_id = u.user_id
            WHERE ud.status = 'pendiente'
            ORDER BY ud.created_at ASC"
        );
        return $stmt->fetchAll();
    }

    public function getOpenMessageThreads(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                mt.thread_id,
                mt.subject,
                COALESCE(CONCAT(u.name, ' ', u.surnames), mt.external_name, 'Externo') as sender_name,
                mt.created_at,
                TIMESTAMPDIFF(HOUR, mt.created_at, NOW()) as hours_elapsed,
                mt.status
            FROM message_threads mt
            LEFT JOIN users u ON mt.sender_id = u.user_id
            WHERE mt.status IN ('pending', 'open')
            AND mt.deleted_at IS NULL
            ORDER BY mt.created_at ASC"
        );
        return $stmt->fetchAll();
    }

    public function getUnassignedUsersCount(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) as count FROM users WHERE role = 'no_agremiado' AND active = 1 AND delete_at IS NULL"
        );
        return (int)($stmt->fetch()['count'] ?? 0);
    }

    public function getLoanKanbanData(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                l.status,
                l.loan_id,
                l.folio,
                CONCAT(u.name, ' ', u.surnames) as name,
                DATEDIFF(CURDATE(), DATE(
                    CASE
                        WHEN l.status = 'borrador' THEN l.application_date
                        WHEN l.status = 'revisión' THEN l.document_review_date
                        WHEN l.status = 'aprobado' THEN l.approval_date
                        WHEN l.status = 'desembolsado' THEN l.disbursement_date
                        ELSE l.application_date
                    END
                )) as days_in_status,
                (SELECT COUNT(*) FROM loan_amortization WHERE loan_id = l.loan_id AND days_overdue > 0) > 0 as is_overdue
            FROM loans l
            JOIN users u ON l.user_id = u.user_id
            WHERE l.deletion_date IS NULL
            ORDER BY l.status, days_in_status DESC"
        );

        $data = $stmt->fetchAll();
        $grouped = [];

        foreach ($data as $loan) {
            $status = $loan['status'];
            if (!isset($grouped[$status])) {
                $grouped[$status] = [];
            }
            $grouped[$status][] = $loan;
        }

        $result = [];
        foreach ($grouped as $status => $loans) {
            $result[] = ['status' => $status, 'loans' => $loans];
        }

        return $result;
    }

    public function getRecentUsers(int $limit = 10): array
    {
        $limit = (int)$limit;
        $stmt = $this->pdo->query(
            "SELECT
                u.user_id,
                CONCAT(u.name, ' ', u.surnames) as name,
                u.email,
                u.role,
                u.created_at,
                DATEDIFF(CURDATE(), DATE(u.created_at)) as days_since_registered
            FROM users u
            WHERE u.delete_at IS NULL
            ORDER BY u.created_at DESC
            LIMIT " . $limit
        );
        return $stmt->fetchAll();
    }

    public function getRecentFailedLogins(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                al.email,
                al.ip_address,
                COUNT(*) as attempts,
                MAX(al.created_at) as last_attempt
            FROM auth_logs al
            WHERE al.success = 0
            AND al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY al.email, al.ip_address
            ORDER BY last_attempt DESC
            LIMIT 10"
        );
        return $stmt->fetchAll();
    }

    public function getFailedMailQueueCount(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) as count FROM mail_queue WHERE status = 'failed'"
        );
        return (int)($stmt->fetch()['count'] ?? 0);
    }
}
