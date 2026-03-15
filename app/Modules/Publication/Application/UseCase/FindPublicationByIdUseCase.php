<?php

namespace App\Modules\Publication\Application\UseCase;

use App\Modules\Publication\Domain\Entity\Publication;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;

final readonly class FindPublicationByIdUseCase
{
    public function __construct(
        private PublicationRepositoryInterface $publicationRepository,
    ) {}

    public function execute(int $id): ?Publication
    {
        return $this->publicationRepository->findById($id);
    }
}
