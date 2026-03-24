<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Infrastructure\Persistence;

use App\Modules\CashBoxes\Domain\Entity\TransactionCategory;
use App\Modules\CashBoxes\Domain\Enum\ContributionCategoryEnum;
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

    public function findFiltered(?string $name = null, ?string $type = null, ?bool $active = null, ?string $contributionCategory = null, string $sortBy = 'name', string $sortOrder = 'ASC'): array
    {
        $query = 'SELECT * FROM transaction_categories WHERE deleted_at IS NULL';
        $params = [];

        if ($name !== null && $name !== '') {
            $query .= ' AND name LIKE :name';
            $params['name'] = '%' . $name . '%';
        }

        if ($type !== null && $type !== '') {
            $query .= ' AND type = :type';
            $params['type'] = $type;
        }

        if ($active !== null) {
            $query .= ' AND active = :active';
            $params['active'] = $active ? 1 : 0;
        }

        if ($contributionCategory !== null && $contributionCategory !== '') {
            $query .= ' AND contribution_category = :contribution_category';
            $params['contribution_category'] = $contributionCategory;
        }

        // Validar sortBy para evitar SQL injection
        $validColumns = ['name', 'type', 'contribution_category', 'active', 'created_at'];
        $sortBy = in_array($sortBy, $validColumns, true) ? $sortBy : 'name';
        $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';

        $query .= ' ORDER BY ' . $sortBy . ' ' . $sortOrder;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function save(TransactionCategory $category): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO transaction_categories (
                category_id, name, type, description, contribution_category, active, created_at, updated_at, deleted_at
            ) VALUES (
                :category_id, :name, :type, :description, :contribution_category, :active, :created_at, :updated_at, :deleted_at
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                type = VALUES(type),
                description = VALUES(description),
                contribution_category = VALUES(contribution_category),
                active = VALUES(active),
                updated_at = VALUES(updated_at),
                deleted_at = VALUES(deleted_at)
        ');

        $stmt->execute([
            'category_id' => $category->categoryId ?: null,
            'name' => $category->name,
            'type' => $category->type->value,
            'description' => $category->description,
            'contribution_category' => $category->contributionCategory?->value,
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
            contributionCategory: ($row['contribution_category'] ?? null) !== null
                ? ContributionCategoryEnum::from($row['contribution_category'])
                : null,
            active: (bool)$row['active'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: $row['updated_at'] ? new DateTimeImmutable($row['updated_at']) : null,
            deletedAt: $row['deleted_at'] ? new DateTimeImmutable($row['deleted_at']) : null,
        );
    }
}
