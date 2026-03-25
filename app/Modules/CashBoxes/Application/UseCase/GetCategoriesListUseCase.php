<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Repository\CategoryRepositoryInterface;

final readonly class GetCategoriesListUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function execute(?string $name = null, ?string $type = null, ?string $status = null, ?string $contributionCategory = null, string $sortBy = 'name', string $sortOrder = 'ASC'): array
    {
        $active = match($status) {
            'active' => true,
            'inactive' => false,
            default => null,
        };

        return [
            'categories' => $this->categoryRepository->findFiltered($name, $type, $active, $contributionCategory, $sortBy, $sortOrder),
            'filters' => [
                'name' => $name,
                'type' => $type,
                'status' => $status,
                'contributionCategory' => $contributionCategory,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
            ]
        ];
    }
}
