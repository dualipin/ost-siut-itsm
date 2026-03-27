<?php

declare(strict_types=1);

namespace App\Modules\Requests\Infrastructure\Persistence;

use App\Modules\Requests\Domain\Entity\RequestType;
use App\Modules\Requests\Domain\Repository\RequestTypeRepositoryInterface;
use DateTimeImmutable;
use PDO;

final readonly class PdoRequestTypeRepository implements RequestTypeRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM request_types ORDER BY name ASC');
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findActive(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM request_types WHERE active = 1 ORDER BY name ASC');
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?RequestType
    {
        $stmt = $this->pdo->prepare('SELECT * FROM request_types WHERE request_type_id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function save(RequestType $type): void
    {
        if ($type->requestTypeId === 0) {
            $stmt = $this->pdo->prepare('
                INSERT INTO request_types (name, description, active)
                VALUES (:name, :description, :active)
            ');
            $stmt->execute([
                'name'        => $type->name,
                'description' => $type->description,
                'active'      => $type->active ? 1 : 0,
            ]);
        } else {
            $stmt = $this->pdo->prepare('
                UPDATE request_types
                SET name = :name, description = :description, active = :active
                WHERE request_type_id = :id
            ');
            $stmt->execute([
                'name'        => $type->name,
                'description' => $type->description,
                'active'      => $type->active ? 1 : 0,
                'id'          => $type->requestTypeId,
            ]);
        }
    }

    private function hydrate(array $row): RequestType
    {
        return new RequestType(
            requestTypeId: (int)$row['request_type_id'],
            name:          $row['name'],
            description:   $row['description'],
            active:        (bool)$row['active'],
            createdAt:     new DateTimeImmutable($row['created_at']),
            updatedAt:     $row['updated_at'] ? new DateTimeImmutable($row['updated_at']) : null,
        );
    }
}
