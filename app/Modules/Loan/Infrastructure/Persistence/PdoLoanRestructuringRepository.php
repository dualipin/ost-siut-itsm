<?php

declare(strict_types=1);

namespace App\Modules\Loan\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Loan\Domain\Repository\LoanRestructuringRepositoryInterface;

class PdoLoanRestructuringRepository extends PdoBaseRepository implements LoanRestructuringRepositoryInterface
{
    protected string $table = 'loan_restructurings';

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} 
                (original_loan_id, new_loan_id, restructure_date, original_balance, 
                 new_balance, reason, approved_by, notes, created_at, updated_at)
                VALUES (:original_loan_id, :new_loan_id, :restructure_date, :original_balance,
                        :new_balance, :reason, :approved_by, :notes, NOW(), NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':original_loan_id' => $data['original_loan_id'],
            ':new_loan_id' => $data['new_loan_id'],
            ':restructure_date' => $data['restructure_date'],
            ':original_balance' => $data['original_balance'],
            ':new_balance' => $data['new_balance'],
            ':reason' => $data['reason'],
            ':approved_by' => $data['approved_by'],
            ':notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByOriginalLoanId(int $loanId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE original_loan_id = :loan_id ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':loan_id' => $loanId]);
        
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function findByNewLoanId(int $loanId): ?object
    {
        $sql = "SELECT * FROM {$this->table} WHERE new_loan_id = :loan_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':loan_id' => $loanId]);
        
        $result = $stmt->fetch(\PDO::FETCH_OBJ);
        return $result ?: null;
    }
}
