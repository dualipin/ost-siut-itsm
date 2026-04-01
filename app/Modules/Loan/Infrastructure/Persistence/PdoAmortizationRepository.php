<?php

namespace App\Modules\Loan\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Loan\Domain\Entity\AmortizationRow;
use App\Modules\Loan\Domain\Enum\PaymentStatusEnum;
use App\Modules\Loan\Domain\Repository\AmortizationRepositoryInterface;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateTimeImmutable;

final class PdoAmortizationRepository extends PdoBaseRepository implements AmortizationRepositoryInterface
{
    public function findById(int $amortizationId): ?AmortizationRow
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loan_amortization WHERE amortization_id = :id");
        $stmt->execute(['id' => $amortizationId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByLoanId(int $loanId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM loan_amortization WHERE loan_id = :loan_id";
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }
        $sql .= " ORDER BY payment_number ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['loan_id' => $loanId]);
        
        return array_map([$this, 'hydrate'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByLoanIdAndVersion(int $loanId, int $version): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM loan_amortization 
            WHERE loan_id = :loan_id AND table_version = :version 
            ORDER BY payment_number ASC
        ");
        $stmt->execute(['loan_id' => $loanId, 'version' => $version]);
        
        return array_map([$this, 'hydrate'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(AmortizationRow $row): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO loan_amortization (
                loan_id, payment_number, income_type_id, scheduled_date,
                initial_balance, principal, ordinary_interest, total_scheduled_payment,
                final_balance, payment_status, table_version, active
            ) VALUES (
                :loan_id, :payment_number, :income_type_id, :scheduled_date,
                :initial_balance, :principal, :ordinary_interest, :total_scheduled_payment,
                :final_balance, :payment_status, :table_version, :active
            )
        ");
        
        $stmt->execute([
            'loan_id' => $row->loanId(),
            'payment_number' => $row->paymentNumber(),
            'income_type_id' => $row->incomeTypeId(),
            'scheduled_date' => $row->scheduledDate()->format('Y-m-d'),
            'initial_balance' => $row->initialBalance()->amount(),
            'principal' => $row->principal()->amount(),
            'ordinary_interest' => $row->ordinaryInterest()->amount(),
            'total_scheduled_payment' => $row->totalScheduledPayment()->amount(),
            'final_balance' => $row->finalBalance()->amount(),
            'payment_status' => $row->paymentStatus()->value,
            'table_version' => $row->tableVersion(),
            'active' => $row->isActive() ? 1 : 0
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    public function saveAll(array $rows): void
    {
        foreach ($rows as $row) {
            $this->save($row);
        }
    }

    public function update(AmortizationRow $row): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE loan_amortization SET
                payment_status = :payment_status,
                actual_payment_date = :actual_payment_date,
                actual_paid_amount = :actual_paid_amount,
                days_overdue = :days_overdue,
                generated_default_interest = :generated_default_interest,
                paid_by = :paid_by,
                active = :active
            WHERE amortization_id = :amortization_id
        ");
        
        $stmt->execute([
            'amortization_id' => $row->amortizationId(),
            'payment_status' => $row->paymentStatus()->value,
            'actual_payment_date' => $row->actualPaymentDate()?->format('Y-m-d H:i:s'),
            'actual_paid_amount' => $row->actualPaidAmount()->amount(),
            'days_overdue' => $row->daysOverdue(),
            'generated_default_interest' => $row->generatedDefaultInterest()->amount(),
            'paid_by' => $row->paidBy(),
            'active' => $row->isActive() ? 1 : 0
        ]);
    }

    public function deactivateByLoanId(int $loanId): void
    {
        $stmt = $this->pdo->prepare("UPDATE loan_amortization SET active = 0 WHERE loan_id = :loan_id");
        $stmt->execute(['loan_id' => $loanId]);
    }

    public function getLatestVersionByLoanId(int $loanId): int
    {
        $stmt = $this->pdo->prepare("SELECT MAX(table_version) FROM loan_amortization WHERE loan_id = :loan_id");
        $stmt->execute(['loan_id' => $loanId]);
        
        return (int) $stmt->fetchColumn() ?: 1;
    }

    private function hydrate(array $row): AmortizationRow
    {
        return new AmortizationRow(
            amortizationId: (int) $row['amortization_id'],
            loanId: (int) $row['loan_id'],
            paymentNumber: (int) $row['payment_number'],
            incomeTypeId: (int) $row['income_type_id'],
            scheduledDate: new DateTimeImmutable($row['scheduled_date']),
            initialBalance: Money::fromFloat((float) $row['initial_balance']),
            principal: Money::fromFloat((float) $row['principal']),
            ordinaryInterest: Money::fromFloat((float) $row['ordinary_interest']),
            totalScheduledPayment: Money::fromFloat((float) $row['total_scheduled_payment']),
            finalBalance: Money::fromFloat((float) $row['final_balance']),
            paymentStatus: PaymentStatusEnum::from($row['payment_status']),
            actualPaymentDate: $row['actual_payment_date'] ? new DateTimeImmutable($row['actual_payment_date']) : null,
            actualPaidAmount: Money::fromFloat((float) ($row['actual_paid_amount'] ?? 0)),
            daysOverdue: (int) ($row['days_overdue'] ?? 0),
            generatedDefaultInterest: Money::fromFloat((float) ($row['generated_default_interest'] ?? 0)),
            paidBy: $row['paid_by'] ? (int) $row['paid_by'] : null,
            paymentReceipt: $row['payment_receipt'],
            tableVersion: (int) $row['table_version'],
            active: (bool) $row['active']
        );
    }
}
