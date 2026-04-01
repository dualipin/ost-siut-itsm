<?php

namespace App\Modules\Loan\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Loan\Domain\Entity\ExtraordinaryPayment;
use App\Modules\Loan\Domain\Enum\PaymentTypeEnum;
use App\Modules\Loan\Domain\Repository\ExtraordinaryPaymentRepositoryInterface;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateTimeImmutable;

final class PdoExtraordinaryPaymentRepository extends PdoBaseRepository implements ExtraordinaryPaymentRepositoryInterface
{
    public function findById(int $paymentId): ?ExtraordinaryPayment
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loan_extraordinary_payments WHERE extraordinary_payment_id = :id");
        $stmt->execute(['id' => $paymentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByLoanId(int $loanId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM loan_extraordinary_payments 
            WHERE loan_id = :loan_id 
            ORDER BY payment_date DESC
        ");
        $stmt->execute(['loan_id' => $loanId]);
        
        return array_map([$this, 'hydrate'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(ExtraordinaryPayment $payment): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO loan_extraordinary_payments (
                loan_id, payment_type, amount, applied_to_principal,
                applied_to_interest, applied_to_default, regenerated_amortization_table,
                generated_table_version, observations, registered_by
            ) VALUES (
                :loan_id, :payment_type, :amount, :applied_to_principal,
                :applied_to_interest, :applied_to_default, :regenerated_amortization_table,
                :generated_table_version, :observations, :registered_by
            )
        ");
        
        $stmt->execute([
            'loan_id' => $payment->loanId(),
            'payment_type' => $payment->paymentType()->value,
            'amount' => $payment->amount()->amount(),
            'applied_to_principal' => $payment->appliedToPrincipal()?->amount(),
            'applied_to_interest' => $payment->appliedToInterest()?->amount(),
            'applied_to_default' => $payment->appliedToDefault()?->amount(),
            'regenerated_amortization_table' => $payment->regeneratedAmortizationTable() ? 1 : 0,
            'generated_table_version' => $payment->generatedTableVersion(),
            'observations' => $payment->observations(),
            'registered_by' => $payment->registeredBy()
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    private function hydrate(array $row): ExtraordinaryPayment
    {
        return new ExtraordinaryPayment(
            extraordinaryPaymentId: (int) $row['extraordinary_payment_id'],
            loanId: (int) $row['loan_id'],
            paymentType: PaymentTypeEnum::from($row['payment_type']),
            amount: Money::fromFloat((float) $row['amount']),
            paymentDate: new DateTimeImmutable($row['payment_date']),
            appliedToPrincipal: $row['applied_to_principal'] ? Money::fromFloat((float) $row['applied_to_principal']) : null,
            appliedToInterest: $row['applied_to_interest'] ? Money::fromFloat((float) $row['applied_to_interest']) : null,
            appliedToDefault: $row['applied_to_default'] ? Money::fromFloat((float) $row['applied_to_default']) : null,
            regeneratedAmortizationTable: (bool) $row['regenerated_amortization_table'],
            generatedTableVersion: $row['generated_table_version'] ? (int) $row['generated_table_version'] : null,
            observations: $row['observations'],
            paymentReceipt: $row['payment_receipt'],
            registeredBy: $row['registered_by'] ? (int) $row['registered_by'] : null
        );
    }
}
