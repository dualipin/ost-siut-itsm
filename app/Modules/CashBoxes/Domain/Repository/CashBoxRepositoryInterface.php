<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Repository;

use App\Modules\CashBoxes\Domain\Entity\CashBox;
use App\Modules\CashBoxes\Domain\Exception\CashBoxNotFoundException;

interface CashBoxRepositoryInterface
{
    /**
     * @throws CashBoxNotFoundException
     */
    public function findById(int $boxId): CashBox;

    public function save(CashBox $cashBox): void;
    
    /**
     * @return CashBox[]
     */
    public function findAll(): array;

    public function nextId(): int;
}
