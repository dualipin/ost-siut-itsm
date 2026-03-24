<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Repository;

use App\Modules\CashBoxes\Domain\Entity\BoxTransaction;
use App\Modules\CashBoxes\Domain\Entity\BoxTransfer;

interface TransactionRepositoryInterface
{
    public function saveTransaction(BoxTransaction $transaction): void;
    
    public function saveTransfer(BoxTransfer $transfer): void;

    /**
     * @return BoxTransaction[]
     */
    public function findByBoxId(int $boxId): array;
    
    public function nextTransactionId(): int;
    
    public function nextTransferId(): int;
}
