<?php

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Domain\Entity\IncomeType;
use App\Modules\Loan\Domain\Repository\IncomeTypeRepositoryInterface;

class ListIncomeTypesUseCase
{
    public function __construct(
        private IncomeTypeRepositoryInterface $incomeTypeRepository
    ) {}

    /**
     * @return IncomeType[]
     */
    public function execute(): array
    {
        $incomeTypes = $this->incomeTypeRepository->getIncomeTypes();

        return $incomeTypes;
    }
}
