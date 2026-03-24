<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Entity\TransactionCategory;
use App\Modules\CashBoxes\Domain\Enum\ContributionCategoryEnum;
use App\Modules\CashBoxes\Domain\Enum\TransactionTypeEnum;
use App\Modules\CashBoxes\Domain\Repository\CategoryRepositoryInterface;
use DateTimeImmutable;

final readonly class CreateCategoryUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function execute(string $name, ?string $description, string $type, ?string $contributionCategory, bool $active): void
    {
        $typeEnum = TransactionTypeEnum::from($type);
        $contributionCategoryEnum = $typeEnum === TransactionTypeEnum::INCOME && is_string($contributionCategory) && $contributionCategory !== ''
            ? ContributionCategoryEnum::from($contributionCategory)
            : null;

        $category = new TransactionCategory(
            categoryId: 0,
            name: $name,
            type: $typeEnum,
            description: $description,
            contributionCategory: $contributionCategoryEnum,
            active: $active,
            createdAt: new DateTimeImmutable()
        );

        $this->categoryRepository->save($category);
    }
}
