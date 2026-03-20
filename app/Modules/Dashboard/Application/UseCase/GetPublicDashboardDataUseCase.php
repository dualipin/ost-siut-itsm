<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application\UseCase;

use App\Modules\Dashboard\Domain\Repository\PublicDashboardRepositoryInterface;

final readonly class GetPublicDashboardDataUseCase
{
    public function __construct(
        private PublicDashboardRepositoryInterface $publicDashboardRepository,
    ) {}

    public function execute(): array
    {
        return [
            'publicPublications' => $this->publicDashboardRepository->getPublicPublications(),
            'publicFaqs' => $this->publicDashboardRepository->getPublicFaqs(),
            'publicTransparencyDocuments' => $this->publicDashboardRepository->getPublicTransparencyDocuments(),
            'dashboardTitle' => 'Portal Público',
            'dashboardDescription' => 'Información de acceso público de la organización',
        ];
    }
}
