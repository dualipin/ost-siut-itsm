<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Entity\TransactionCategory;
use App\Modules\CashBoxes\Domain\Enum\ContributionCategoryEnum;
use App\Modules\CashBoxes\Domain\Enum\TransactionTypeEnum;
use App\Modules\CashBoxes\Domain\Repository\CategoryRepositoryInterface;
use Exception;

final readonly class UpdateCategoryUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function execute(int $id, string $name, ?string $description, string $type, ?string $contributionCategory, bool $active): void
    {
        $category = $this->categoryRepository->findById($id);
        if (!$category) {
            throw new Exception("Categoría no encontrada");
        }

        $typeEnum = TransactionTypeEnum::from($type);
        $contributionCategoryEnum = $typeEnum === TransactionTypeEnum::INCOME && is_string($contributionCategory) && $contributionCategory !== ''
            ? ContributionCategoryEnum::from($contributionCategory)
            : null;

        $updatedCategory = new TransactionCategory(
            categoryId: $id,
            name: $name,
            description: $description,
            type: $typeEnum,
            contributionCategory: $contributionCategoryEnum,
            active: $active,
            createdAt: $category->createdAt
        );

        $this->categoryRepository->save($updatedCategory);
    }
}
