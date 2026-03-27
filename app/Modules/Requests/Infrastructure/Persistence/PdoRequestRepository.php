<?php

declare(strict_types=1);

namespace App\Modules\Requests\Infrastructure\Persistence;

use App\Modules\Requests\Domain\Entity\Request;
use App\Modules\Requests\Domain\Entity\RequestStatusChange;
use App\Modules\Requests\Domain\Enum\RequestStatusEnum;
use App\Modules\Requests\Domain\Exception\RequestNotFoundException;
use App\Modules\Requests\Domain\Repository\RequestRepositoryInterface;
use DateTimeImmutable;
use PDO;

final readonly class PdoRequestRepository implements RequestRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $requestId): Request
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, rt.name AS type_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS user_full_name
             FROM requests r
             JOIN request_types rt ON rt.request_type_id = r.request_type_id
             JOIN users u ON u.user_id = r.user_id
             WHERE r.request_id = :id AND r.deleted_at IS NULL"
        );
        $stmt->execute(['id' => $requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RequestNotFoundException($requestId);
        }

        return $this->hydrateWithMeta($row);
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, rt.name AS type_name
             FROM requests r
             JOIN request_types rt ON rt.request_type_id = r.request_type_id
             WHERE r.user_id = :user_id AND r.deleted_at IS NULL
             ORDER BY r.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrateWithMeta($row), $rows);
    }

    public function findFiltered(
        ?string $folio = null,
        ?int $requestTypeId = null,
        ?RequestStatusEnum $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'DESC'
    ): array {
        $where  = ['r.deleted_at IS NULL'];
        $params = [];

        if ($folio !== null && $folio !== '') {
            $where[]         = 'r.folio LIKE :folio';
            $params['folio'] = '%' . $folio . '%';
        }

        if ($requestTypeId !== null) {
            $where[]                    = 'r.request_type_id = :type_id';
            $params['type_id'] = $requestTypeId;
        }

        if ($status !== null) {
            $where[]           = 'r.status = :status';
            $params['status']  = $status->value;
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $where[]              = 'DATE(r.created_at) >= :date_from';
            $params['date_from']  = $dateFrom;
        }

        if ($dateTo !== null && $dateTo !== '') {
            $where[]            = 'DATE(r.created_at) <= :date_to';
            $params['date_to']  = $dateTo;
        }

        $validSortFields = ['created_at', 'updated_at', 'folio', 'status'];
        $sortBy          = in_array($sortBy, $validSortFields, true) ? $sortBy : 'created_at';
        $sortOrder       = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT r.*, rt.name AS type_name,
                       CONCAT(u.first_name, ' ', u.last_name) AS user_full_name
                FROM requests r
                JOIN request_types rt ON rt.request_type_id = r.request_type_id
                JOIN users u ON u.user_id = r.user_id
                WHERE " . implode(' AND ', $where) .
               " ORDER BY r.{$sortBy} {$sortOrder}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrateWithMeta($row), $rows);
    }

    public function save(Request $request): int
    {
        if ($request->requestId === 0) {
            // INSERT
            $stmt = $this->pdo->prepare('
                INSERT INTO requests
                    (user_id, request_type_id, folio, reason, status,
                     admin_notes, resolved_by, resolved_at, created_at, updated_at)
                VALUES
                    (:user_id, :request_type_id, :folio, :reason, :status,
                     :admin_notes, :resolved_by, :resolved_at, :created_at, :updated_at)
            ');
            $stmt->execute([
                'user_id'         => $request->userId,
                'request_type_id' => $request->requestTypeId,
                'folio'           => $request->folio,
                'reason'          => $request->reason,
                'status'          => $request->status->value,
                'admin_notes'     => $request->adminNotes,
                'resolved_by'     => $request->resolvedBy,
                'resolved_at'     => $request->resolvedAt?->format('Y-m-d H:i:s'),
                'created_at'      => $request->createdAt->format('Y-m-d H:i:s'),
                'updated_at'      => $request->updatedAt?->format('Y-m-d H:i:s'),
            ]);
            return (int)$this->pdo->lastInsertId();
        }

        // UPDATE
        $stmt = $this->pdo->prepare('
            UPDATE requests
            SET status      = :status,
                admin_notes = :admin_notes,
                resolved_by = :resolved_by,
                resolved_at = :resolved_at,
                updated_at  = :updated_at
            WHERE request_id = :request_id
        ');
        $stmt->execute([
            'status'      => $request->status->value,
            'admin_notes' => $request->adminNotes,
            'resolved_by' => $request->resolvedBy,
            'resolved_at' => $request->resolvedAt?->format('Y-m-d H:i:s'),
            'updated_at'  => $request->updatedAt?->format('Y-m-d H:i:s'),
            'request_id'  => $request->requestId,
        ]);

        return $request->requestId;
    }

    public function saveStatusHistory(
        int $requestId,
        ?int $changedBy,
        ?string $statusFrom,
        string $statusTo,
        ?string $notes
    ): void {
        $stmt = $this->pdo->prepare('
            INSERT INTO request_status_history
                (request_id, changed_by, status_from, status_to, notes, changed_at)
            VALUES
                (:request_id, :changed_by, :status_from, :status_to, :notes, NOW())
        ');
        $stmt->execute([
            'request_id'  => $requestId,
            'changed_by'  => $changedBy,
            'status_from' => $statusFrom,
            'status_to'   => $statusTo,
            'notes'       => $notes,
        ]);
    }

    public function nextFolio(): string
    {
        $year = (int)date('Y');
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM requests WHERE YEAR(created_at) = :year"
        );
        $stmt->execute(['year' => $year]);
        $count = (int)$stmt->fetchColumn();

        return sprintf('SOL-%d-%04d', $year, $count + 1);
    }

    public function findStatusHistory(int $requestId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT h.*, CONCAT(u.first_name, ' ', u.last_name) AS changed_by_name
             FROM request_status_history h
             LEFT JOIN users u ON u.user_id = h.changed_by
             WHERE h.request_id = :request_id
             ORDER BY h.changed_at ASC"
        );
        $stmt->execute(['request_id' => $requestId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => new RequestStatusChange(
            historyId:   (int)$row['history_id'],
            requestId:   (int)$row['request_id'],
            changedBy:   $row['changed_by'] !== null ? (int)$row['changed_by'] : null,
            statusFrom:  $row['status_from'],
            statusTo:    $row['status_to'],
            notes:       $row['notes'],
            changedAt:   new DateTimeImmutable($row['changed_at']),
        ), $rows);
    }

    private function hydrate(array $row): Request
    {
        return new Request(
            requestId:     (int)$row['request_id'],
            userId:        (int)$row['user_id'],
            requestTypeId: (int)$row['request_type_id'],
            folio:         $row['folio'],
            reason:        $row['reason'],
            status:        RequestStatusEnum::from($row['status']),
            adminNotes:    $row['admin_notes'],
            resolvedBy:    $row['resolved_by'] !== null ? (int)$row['resolved_by'] : null,
            resolvedAt:    $row['resolved_at'] ? new DateTimeImmutable($row['resolved_at']) : null,
            createdAt:     new DateTimeImmutable($row['created_at']),
            updatedAt:     $row['updated_at'] ? new DateTimeImmutable($row['updated_at']) : null,
            deletedAt:     $row['deleted_at'] ? new DateTimeImmutable($row['deleted_at']) : null,
        );
    }

    /**
     * Hydrate with extra joined columns (type_name, user_full_name) stored as public properties via DTO pattern.
     * We use a simple array-enriched approach: attach extra data to a stdClass wrapper accessible in templates.
     */
    private function hydrateWithMeta(array $row): object
    {
        $entity = $this->hydrate($row);

        return new class($entity, $row) {
            public function __construct(
                public readonly Request $request,
                public readonly array   $meta,
            ) {
            }

            public function __get(string $name): mixed
            {
                if (property_exists($this->request, $name)) {
                    return $this->request->{$name};
                }
                return $this->meta[$name] ?? null;
            }

            public function isPending(): bool { return $this->request->isPending(); }
            public function isOwnedBy(int $id): bool { return $this->request->isOwnedBy($id); }
            public function canTransitionTo(\App\Modules\Requests\Domain\Enum\RequestStatusEnum $s): bool {
                return $this->request->canTransitionTo($s);
            }
        };
    }
}
