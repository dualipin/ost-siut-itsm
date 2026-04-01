<?php

namespace App\Modules\Loan\Domain\Repository;

use App\Modules\Loan\Domain\Entity\Receipt;

interface ReceiptRepositoryInterface
{
    public function findById(int $receiptId): ?Receipt;

    public function findByLoanId(int $loanId): array;

    public function findByFolio(string $folio): ?Receipt;

    public function save(Receipt $receipt): int;

    public function getNextFolioNumber(): int;
}
