<?php

namespace App\Modules\Loan\Domain\Service;

use App\Modules\Loan\Domain\Repository\SaverUserRepositoryInterface;
use App\Modules\Loan\Domain\ValueObject\InterestRate;
use App\Shared\Domain\Enum\RoleEnum;

final readonly class InterestRateProvider
{
    public function __construct(
        private SaverUserRepositoryInterface $saverUserRepository
    ) {}

    /**
     * Get standard interest rate based on user role and saver status
     * 
     * Rates:
     * - Agremiado + saver: 6%
     * - Agremiado + non-saver: 7.5%
     * - NoAgremiado + saver: 8%
     * - NoAgremiado + non-saver: 9.5%
     */
    public function getStandardRate(int $userId, RoleEnum $role): InterestRate
    {
        $isSaver = $this->saverUserRepository->isSaver($userId);
        
        return match ([$role, $isSaver]) {
            [RoleEnum::Agremiado, true] => InterestRate::fromPercentage(6.0),
            [RoleEnum::Agremiado, false] => InterestRate::fromPercentage(7.5),
            [RoleEnum::NoAgremiado, true] => InterestRate::fromPercentage(8.0),
            [RoleEnum::NoAgremiado, false] => InterestRate::fromPercentage(9.5),
            default => InterestRate::fromPercentage(9.5), // Default for other roles
        };
    }
}
