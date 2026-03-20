<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Dashboard\Domain\Repository\FinanzasRepositoryInterface;
use DateTimeImmutable;

final class PdoFinanzasRepository extends PdoBaseRepository implements FinanzasRepositoryInterface
{
    public function getScheduledPaymentsForDate(DateTimeImmutable $date): array
    {
        $dateStr = $date->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            "SELECT
                la.amortization_id,
                l.folio,
                CONCAT(u.name, ' ', u.surnames) as user_name,
                la.payment_number,
                la.principal,
                la.ordinary_interest as interest,
                la.total_scheduled_payment as total,
                la.payment_status as status
            FROM loan_amortization la
            JOIN loans l ON la.loan_id = l.loan_id
            JOIN users u ON l.user_id = u.user_id
            WHERE DATE(la.scheduled_date) = ?
            AND la.active = 1
            ORDER BY la.scheduled_date ASC"
        );
        $stmt->execute([$dateStr]);
        return $stmt->fetchAll();
    }

    public function getTotalScheduledForDate(DateTimeImmutable $date): float
    {
        $dateStr = $date->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(total_scheduled_payment), 0) as total FROM loan_amortization
            WHERE DATE(scheduled_date) = ?
            AND active = 1"
        );
        $stmt->execute([$dateStr]);
        return (float)($stmt->fetch()['total'] ?? 0);
    }

    public function getOverduePayments(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                la.amortization_id,
                l.folio,
                CONCAT(u.name, ' ', u.surnames) as user_name,
                la.payment_number,
                la.days_overdue,
                la.total_scheduled_payment,
                la.generated_default_interest
            FROM loan_amortization la
            JOIN loans l ON la.loan_id = l.loan_id
            JOIN users u ON l.user_id = u.user_id
            WHERE DATE(la.scheduled_date) < CURDATE()
            AND la.payment_status = 'pendiente'
            AND la.active = 1
            ORDER BY la.days_overdue DESC"
        );
        return $stmt->fetchAll();
    }

    public function getPaymentsNext15Days(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                DATE(la.scheduled_date) as date,
                COUNT(*) as count,
                SUM(la.total_scheduled_payment) as total
            FROM loan_amortization la
            WHERE DATE(la.scheduled_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)
            AND la.active = 1
            GROUP BY DATE(la.scheduled_date)
            ORDER BY la.scheduled_date ASC"
        );
        return $stmt->fetchAll();
    }

    public function getLoansInDefault(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                l.loan_id,
                l.folio,
                CONCAT(u.name, ' ', u.surnames) as user_name,
                MIN(la.days_overdue) as oldest_overdue_days,
                SUM(la.generated_default_interest) as default_interest_accumulated
            FROM loans l
            JOIN loan_amortization la ON l.loan_id = la.loan_id
            JOIN users u ON l.user_id = u.user_id
            WHERE la.days_overdue > 0
            AND la.active = 1
            GROUP BY l.loan_id
            ORDER BY oldest_overdue_days DESC"
        );
        return $stmt->fetchAll();
    }

    public function getPendingLegalDocuments(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                lld.legal_doc_id,
                l.folio,
                lld.document_type,
                l.loan_id,
                lld.generation_date,
                DATEDIFF(CURDATE(), DATE(lld.generation_date)) as days_waiting
            FROM loan_legal_documents lld
            JOIN loans l ON lld.loan_id = l.loan_id
            WHERE lld.requires_finance_validation = 1
            AND lld.validated_by_finance = 0
            ORDER BY lld.generation_date ASC"
        );
        return $stmt->fetchAll();
    }

    public function getPendingUserSignatures(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                lld.legal_doc_id,
                l.folio,
                lld.document_type,
                l.loan_id,
                DATEDIFF(CURDATE(), DATE(lld.generation_date)) as days_waiting
            FROM loan_legal_documents lld
            JOIN loans l ON lld.loan_id = l.loan_id
            WHERE lld.requires_user_signature = 1
            AND lld.user_signature_date IS NULL
            ORDER BY lld.generation_date ASC"
        );
        return $stmt->fetchAll();
    }

    public function getActiveRestructurings(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                lr.restructuring_id,
                original.folio as original_folio,
                new.folio as new_folio,
                lr.new_total_amount,
                lr.new_interest_rate,
                lr.new_term_fortnights,
                lr.restructuring_date
            FROM loan_restructurings lr
            JOIN loans original ON lr.original_loan_id = original.loan_id
            JOIN loans new ON lr.new_loan_id = new.loan_id
            WHERE new.status IN ('borrador', 'revisión')
            ORDER BY lr.restructuring_date DESC"
        );
        return $stmt->fetchAll();
    }

    public function getMailQueueStatus(): array
    {
        $pending = $this->pdo->query("SELECT COUNT(*) as count FROM mail_queue WHERE status = 'pending'")
            ->fetch()['count'] ?? 0;

        $failed = $this->pdo->query("SELECT COUNT(*) as count FROM mail_queue WHERE status = 'failed'")
            ->fetch()['count'] ?? 0;

        return [
            'pending' => (int)$pending,
            'failed' => (int)$failed,
        ];
    }
}
