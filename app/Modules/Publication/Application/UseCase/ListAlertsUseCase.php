<?php

namespace App\Modules\Publication\Application\UseCase;

use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;

final readonly class ListAlertsUseCase
{
    public function __construct(
        public PublicationRepositoryInterface $publicationRepository,
    ) {}

    public function execute(): array
    {
        $alerts = $this->publicationRepository->findByType(
            PublicationTypeEnum::Alert,
        );
        return $alerts;
    }
}
