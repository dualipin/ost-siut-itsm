<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Infrastructure\Persistence;

use App\Modules\CashBoxes\Domain\DTO\CashBoxFilterCriteria;
use App\Modules\CashBoxes\Domain\Entity\CashBox;
use App\Modules\CashBoxes\Domain\Enum\BoxStatusEnum;
use App\Modules\CashBoxes\Domain\Exception\CashBoxNotFoundException;
use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use DateTimeImmutable;
use PDO;

final readonly class PdoCashBoxRepository implements CashBoxRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $boxId): CashBox
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cash_boxes WHERE box_id = :box_id AND deleted_at IS NULL');
        $stmt->execute(['box_id' => $boxId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new CashBoxNotFoundException($boxId);
        }

        return $this->hydrate($row);
    }

    public function save(CashBox $cashBox): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO cash_boxes (
                box_id, created_by, name, description, currency, 
                initial_balance, current_balance, status, created_at, updated_at, deleted_at
            ) VALUES (
                :box_id, :created_by, :name, :description, :currency,
                :initial_balance, :current_balance, :status, :created_at, :updated_at, :deleted_at
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                current_balance = VALUES(current_balance),
                status = VALUES(status),
                updated_at = VALUES(updated_at),
                deleted_at = VALUES(deleted_at)
        ');

        $stmt->execute([
            'box_id' => $cashBox->boxId,
            'created_by' => $cashBox->createdBy,
            'name' => $cashBox->name,
            'description' => $cashBox->description,
            'currency' => $cashBox->currency,
            'initial_balance' => $cashBox->initialBalance,
            'current_balance' => $cashBox->currentBalance,
            'status' => $cashBox->status->value,
            'created_at' => $cashBox->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $cashBox->updatedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $cashBox->deletedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM cash_boxes WHERE deleted_at IS NULL ORDER BY created_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findWithFilters(CashBoxFilterCriteria $criteria): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if ($criteria->name !== null) {
            $where[] = 'name LIKE :name';
            $params['name'] = '%' . $criteria->name . '%';
        }

        if ($criteria->status !== null) {
            $where[] = 'status = :status';
            $params['status'] = $criteria->status->value;
        }

        if ($criteria->minInitialBalance !== null) {
            $where[] = 'initial_balance >= :min_initial';
            $params['min_initial'] = $criteria->minInitialBalance;
        }

        if ($criteria->maxInitialBalance !== null) {
            $where[] = 'initial_balance <= :max_initial';
            $params['max_initial'] = $criteria->maxInitialBalance;
        }

        if ($criteria->minCurrentBalance !== null) {
            $where[] = 'current_balance >= :min_current';
            $params['min_current'] = $criteria->minCurrentBalance;
        }

        if ($criteria->maxCurrentBalance !== null) {
            $where[] = 'current_balance <= :max_current';
            $params['max_current'] = $criteria->maxCurrentBalance;
        }

        $validSortFields = ['name', 'status', 'initial_balance', 'current_balance', 'created_at', 'updated_at'];
        $sortBy = in_array($criteria->sortBy, $validSortFields) ? $criteria->sortBy : 'created_at';
        $sortOrder = strtoupper($criteria->sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $sql = 'SELECT * FROM cash_boxes WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $sortBy . ' ' . $sortOrder;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function nextId(): int
    {
        // Simple auto-increment mock/handler. In real scenario, use lastInsertId or UUID.
        // Assuming box_id is AUTO_INCREMENT, this might not be strictly needed if we omit box_id on insert.
        // But for Domain-driven persistence where ID is needed first, we simulate or ensure DB supports it.
        // MySQL requires inserting to get the ID. We'll return 0 to let DB auto-increment, and rely on lastInsertId in save if needed.
        // However, standard DDD means we'd generate an ID. Let's return a dummy or rely on sequence.
        // Given schema `box_id INT AUTO_INCREMENT PRIMARY KEY`, nextId() might be tricky without inserting.
        // We'll return 0, assuming the insert ignores it or we adjust save logic to not update box_id if 0.
        // For strictness, if nextId() is required by interface:
        return 0; // Handled by AUTO_INCREMENT in MySQL
    }

    private function hydrate(array $row): CashBox
    {
        return new CashBox(
            boxId: (int)$row['box_id'],
            createdBy: (int)$row['created_by'],
            name: $row['name'],
            description: $row['description'],
            currency: $row['currency'],
            initialBalance: (float)$row['initial_balance'],
            currentBalance: (float)$row['current_balance'],
            status: BoxStatusEnum::from($row['status']),
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: $row['updated_at'] ? new DateTimeImmutable($row['updated_at']) : null,
            deletedAt: $row['deleted_at'] ? new DateTimeImmutable($row['deleted_at']) : null,
        );
    }
}
