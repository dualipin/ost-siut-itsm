<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Infrastructure\Persistence;

use App\Modules\CashBoxes\Domain\Entity\TransactionCategory;
use App\Modules\CashBoxes\Domain\Enum\TransactionTypeEnum;
use App\Modules\CashBoxes\Domain\Repository\CategoryRepositoryInterface;
use DateTimeImmutable;
use PDO;

final readonly class PdoCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM transaction_categories WHERE deleted_at IS NULL ORDER BY name ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findById(int $categoryId): ?TransactionCategory
    {
        $stmt = $this->pdo->prepare('SELECT * FROM transaction_categories WHERE category_id = :category_id AND deleted_at IS NULL');
        $stmt->execute(['category_id' => $categoryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function save(TransactionCategory $category): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO transaction_categories (
                category_id, name, type, description, active, created_at, updated_at, deleted_at
            ) VALUES (
                :category_id, :name, :type, :description, :active, :created_at, :updated_at, :deleted_at
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                type = VALUES(type),
                description = VALUES(description),
                active = VALUES(active),
                updated_at = VALUES(updated_at),
                deleted_at = VALUES(deleted_at)
        ');

        $stmt->execute([
            'category_id' => $category->categoryId ?: null,
            'name' => $category->name,
            'type' => $category->type->value,
            'description' => $category->description,
            'active' => $category->active ? 1 : 0,
            'created_at' => $category->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $category->updatedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $category->deletedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    private function hydrate(array $row): TransactionCategory
    {
        return new TransactionCategory(
            categoryId: (int)$row['category_id'],
            name: $row['name'],
            type: TransactionTypeEnum::from($row['type']),
            description: $row['description'],
            active: (bool)$row['active'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: $row['updated_at'] ? new DateTimeImmutable($row['updated_at']) : null,
            deletedAt: $row['deleted_at'] ? new DateTimeImmutable($row['deleted_at']) : null,
        );
    }
}
