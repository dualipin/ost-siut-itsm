<?php

namespace App\Modules\Publication\Application\UseCase;

use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;

final readonly class ListNewsUseCase
{
    public function __construct(
        public PublicationRepositoryInterface $publicationRepository,
    ) {}

    public function execute(): array
    {
        $news = $this->publicationRepository->findByType(
            PublicationTypeEnum::News,
        );
        return $news;
    }
}
