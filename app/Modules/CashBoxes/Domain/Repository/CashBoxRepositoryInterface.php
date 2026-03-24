<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Repository;

use App\Modules\CashBoxes\Domain\Entity\CashBox;
use App\Modules\CashBoxes\Domain\Exception\CashBoxNotFoundException;
use App\Modules\CashBoxes\Domain\Enum\BoxStatusEnum;

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

    /**
     * @return CashBox[]
     */
    public function findFiltered(?string $name = null, ?BoxStatusEnum $status = null, ?float $minInitialBalance = null, ?float $maxInitialBalance = null, ?float $minCurrentBalance = null, ?float $maxCurrentBalance = null, string $sortBy = 'created_at', string $sortOrder = 'DESC'): array;

    public function nextId(): int;
}
