<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Repository;

use App\Modules\CashBoxes\Domain\Entity\TransactionCategory;

interface CategoryRepositoryInterface
{
    /**
     * @return TransactionCategory[]
     */
    public function findAll(): array;

    public function findById(int $categoryId): ?TransactionCategory;

    public function save(TransactionCategory $category): void;
}
