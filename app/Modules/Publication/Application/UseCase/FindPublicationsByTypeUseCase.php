<?php

namespace App\Modules\Publication\Application\UseCase;

use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;

final readonly class FindPublicationsByTypeUseCase
{
    public function __construct(
        private PublicationRepositoryInterface $publicationRepository,
    ) {}

    public function execute(PublicationTypeEnum $type): array
    {
        return $this->publicationRepository->findByType($type);
    }
}
