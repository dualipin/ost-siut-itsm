<?php

namespace App\Modules\Loan\Domain\Repository;

use App\Modules\Loan\Domain\Entity\IncomeType;

interface IncomeTypeRepositoryInterface
{
    /**
     * @param bool $all
     * @return IncomeType[]
     */
    public function getIncomeTypes(bool $all = false): array;
}