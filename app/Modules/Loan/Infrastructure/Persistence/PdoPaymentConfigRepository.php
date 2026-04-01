<?php

namespace App\Modules\Loan\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Loan\Domain\Repository\PaymentConfigRepositoryInterface;

final class PdoPaymentConfigRepository extends PdoBaseRepository implements PaymentConfigRepositoryInterface
{
    public function findByLoanId(int $loanId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loan_payment_configuration WHERE loan_id = :loan_id");
        $stmt->execute(['loan_id' => $loanId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function save(array $config): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO loan_payment_configuration (
                loan_id, income_type_id, total_amount_to_deduct, number_of_installments,
                amount_per_installment, interest_method, supporting_document_path, document_status
            ) VALUES (
                :loan_id, :income_type_id, :total_amount_to_deduct, :number_of_installments,
                :amount_per_installment, :interest_method, :supporting_document_path, :document_status
            )
        ");
        
        $stmt->execute($config);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $configId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE loan_payment_configuration SET
                document_status = :document_status,
                document_validation_date = :document_validation_date
            WHERE payment_config_id = :config_id
        ");
        
        $stmt->execute(array_merge($data, ['config_id' => $configId]));
    }

    public function deleteByLoanId(int $loanId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM loan_payment_configuration WHERE loan_id = :loan_id");
        $stmt->execute(['loan_id' => $loanId]);
    }
}
