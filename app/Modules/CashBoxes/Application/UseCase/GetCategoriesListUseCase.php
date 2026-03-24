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

    public function execute(): array
    {
        return [
            'categories' => $this->categoryRepository->findAll()
        ];
    }
}
