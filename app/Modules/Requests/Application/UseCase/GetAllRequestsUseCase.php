<?php

declare(strict_types=1);

namespace App\Modules\Requests\Application\UseCase;

use App\Modules\Requests\Domain\Repository\RequestRepositoryInterface;
use App\Modules\Requests\Domain\Enum\RequestStatusEnum;

final readonly class GetAllRequestsUseCase
{
    public function __construct(
        private RequestRepositoryInterface $requestRepository,
    ) {
    }

    public function execute(
        ?string $folio = null,
        ?int $requestTypeId = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'DESC',
    ): array {
        $statusEnum = null;
        if ($status !== null && $status !== '') {
            $statusEnum = RequestStatusEnum::tryFrom($status);
        }

        $requests = $this->requestRepository->findFiltered(
            folio: $folio,
            requestTypeId: $requestTypeId ?: null,
            status: $statusEnum,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
        );

        return [
            'requests' => $requests,
            'filters' => [
                'folio'           => $folio,
                'request_type_id' => $requestTypeId,
                'status'          => $status,
                'date_from'       => $dateFrom,
                'date_to'         => $dateTo,
                'sort_by'         => $sortBy,
                'sort_order'      => $sortOrder,
            ],
        ];
    }
}
