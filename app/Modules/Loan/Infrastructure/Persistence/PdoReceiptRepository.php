<?php

namespace App\Modules\Loan\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Loan\Domain\Entity\Receipt;
use App\Modules\Loan\Domain\Enum\ReceiptTypeEnum;
use App\Modules\Loan\Domain\Repository\ReceiptRepositoryInterface;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateTimeImmutable;

final class PdoReceiptRepository extends PdoBaseRepository implements ReceiptRepositoryInterface
{
    public function findById(int $receiptId): ?Receipt
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loan_receipts WHERE receipt_id = :id");
        $stmt->execute(['id' => $receiptId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByLoanId(int $loanId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loan_receipts WHERE loan_id = :loan_id ORDER BY issue_date DESC");
        $stmt->execute(['loan_id' => $loanId]);
        
        return array_map([$this, 'hydrate'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByFolio(string $folio): ?Receipt
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loan_receipts WHERE receipt_folio = :folio");
        $stmt->execute(['folio' => $folio]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function save(Receipt $receipt): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO loan_receipts (
                loan_id, amortization_id, receipt_type, receipt_folio,
                amount, description, pdf_path
            ) VALUES (
                :loan_id, :amortization_id, :receipt_type, :receipt_folio,
                :amount, :description, :pdf_path
            )
        ");
        
        $stmt->execute([
            'loan_id' => $receipt->loanId(),
            'amortization_id' => $receipt->amortizationId(),
            'receipt_type' => $receipt->receiptType()->value,
            'receipt_folio' => $receipt->receiptFolio(),
            'amount' => $receipt->amount()->amount(),
            'description' => $receipt->description(),
            'pdf_path' => $receipt->pdfPath()
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    public function getNextFolioNumber(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(CAST(SUBSTRING(receipt_folio, -6) AS UNSIGNED)) FROM loan_receipts");
        $lastNumber = (int) $stmt->fetchColumn();
        
        return $lastNumber + 1;
    }

    private function hydrate(array $row): Receipt
    {
        return new Receipt(
            receiptId: (int) $row['receipt_id'],
            loanId: (int) $row['loan_id'],
            amortizationId: $row['amortization_id'] ? (int) $row['amortization_id'] : null,
            receiptType: ReceiptTypeEnum::from($row['receipt_type']),
            receiptFolio: $row['receipt_folio'],
            amount: Money::fromFloat((float) $row['amount']),
            description: $row['description'],
            issueDate: new DateTimeImmutable($row['issue_date']),
            pdfPath: $row['pdf_path']
        );
    }
}
