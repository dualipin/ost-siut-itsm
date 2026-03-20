<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Dashboard\Domain\Repository\MiPrestamoRepositoryInterface;

final class PdoMiPrestamoRepository extends PdoBaseRepository implements MiPrestamoRepositoryInterface
{
    public function hasActiveLoan(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as count FROM loans WHERE user_id = ? AND status = 'desembolsado' AND deletion_date IS NULL"
        );
        $stmt->execute([$userId]);
        return (int)($stmt->fetch()['count'] ?? 0) > 0;
    }

    public function getActiveLoan(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                l.loan_id,
                l.folio,
                l.approved_amount as original_amount,
                l.approved_amount,
                l.applied_interest_rate as interest_rate,
                l.outstanding_balance,
                l.term_fortnights,
                l.first_payment_date,
                l.last_scheduled_payment_date,
                l.disbursement_date
            FROM loans l
            WHERE l.user_id = ?
            AND l.status = 'desembolsado'
            AND l.deletion_date IS NULL
            LIMIT 1"
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? (array)$result : null;
    }

    public function getAmortizationTable(int $loanId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                la.amortization_id,
                la.payment_number,
                la.scheduled_date,
                la.principal,
                la.ordinary_interest as interest,
                la.total_scheduled_payment as total,
                la.final_balance as balance,
                la.payment_status as status,
                COALESCE(la.days_overdue, 0) as days_overdue,
                la.generated_default_interest as default_interest
            FROM loan_amortization la
            WHERE la.loan_id = ?
            AND la.active = 1
            ORDER BY la.payment_number ASC"
        );
        $stmt->execute([$loanId]);
        return $stmt->fetchAll();
    }

    public function getUserDocuments(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                ud.document_id,
                ud.document_type,
                ud.status,
                ud.observation,
                ud.created_at,
                COALESCE(CONCAT(u.name, ' ', u.surnames), 'Sistema') as validated_by,
                ud.updated_at as validated_date
            FROM user_documents ud
            LEFT JOIN users u ON ud.validated_by = u.user_id
            WHERE ud.user_id = ?
            ORDER BY ud.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getPendingSignaturesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                lld.legal_doc_id,
                lld.document_type,
                l.folio,
                lld.requires_user_signature,
                lld.user_signature_date
            FROM loan_legal_documents lld
            JOIN loans l ON lld.loan_id = l.loan_id
            WHERE l.user_id = ?
            AND lld.requires_user_signature = 1
            AND lld.user_signature_date IS NULL"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getRecentPublications(int $limit = 4): array
    {
        $limit = (int)$limit;
        $stmt = $this->pdo->query(
            "SELECT
                p.publication_id,
                p.title,
                p.created_at,
                p.publication_type
            FROM publications p
            WHERE (p.expiration_date IS NULL OR p.expiration_date >= CURDATE())
            ORDER BY p.created_at DESC
            LIMIT " . $limit
        );
        return $stmt->fetchAll();
    }
}
