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

    /**
     * @return TransactionCategory[]
     */
    public function findFiltered(?string $name = null, ?string $type = null, ?bool $active = null, string $sortBy = 'name', string $sortOrder = 'ASC'): array;

    public function save(TransactionCategory $category): void;
}
