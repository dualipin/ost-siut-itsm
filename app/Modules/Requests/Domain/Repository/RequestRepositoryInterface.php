<?php

declare(strict_types=1);

namespace App\Modules\Requests\Domain\Repository;

use App\Modules\Requests\Domain\Entity\Request;
use App\Modules\Requests\Domain\Enum\RequestStatusEnum;
use App\Modules\Requests\Domain\Exception\RequestNotFoundException;

interface RequestRepositoryInterface
{
    /**
     * @throws RequestNotFoundException
     */
    public function findById(int $requestId);

    /**
     * @return Request[]
     */
    public function findByUserId(int $userId): array;

    /**
     * @return Request[]
     */
    public function findFiltered(
        ?string $folio = null,
        ?int $requestTypeId = null,
        ?RequestStatusEnum $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'DESC'
    ): array;

    public function save(Request $request): int;

    public function saveStatusHistory(int $requestId, ?int $changedBy, ?string $statusFrom, string $statusTo, ?string $notes): void;

    public function nextFolio(): string;
}
