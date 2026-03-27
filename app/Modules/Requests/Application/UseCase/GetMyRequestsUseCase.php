<?php

declare(strict_types=1);

namespace App\Modules\Requests\Application\UseCase;

use App\Modules\Requests\Domain\Repository\RequestRepositoryInterface;
use App\Modules\Requests\Domain\Enum\RequestStatusEnum;

final readonly class GetMyRequestsUseCase
{
    public function __construct(
        private RequestRepositoryInterface $requestRepository,
    ) {
    }

    public function execute(int $userId): array
    {
        $requests = $this->requestRepository->findByUserId($userId);

        return ['requests' => $requests];
    }
}
