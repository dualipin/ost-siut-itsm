<?php

namespace App\Modules\Loan\Domain\Exception;

use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use Exception;

final class InvalidLoanStatusException extends Exception
{
    public static function forOperation(string $operation, LoanStatusEnum $currentStatus, array $requiredStatuses): self
    {
        $requiredStatusList = implode(', ', array_map(fn($s) => $s->value, $requiredStatuses));
        return new self(
            "Cannot {$operation}: loan status is '{$currentStatus->value}', " .
            "but must be one of: {$requiredStatusList}"
        );
    }

    public static function cannotReview(LoanStatusEnum $currentStatus): self
    {
        return new self(
            "Cannot review loan: status is '{$currentStatus->value}', " .
            "but must be 'solicitado'"
        );
    }

    public static function cannotValidate(LoanStatusEnum $currentStatus): self
    {
        return new self(
            "Cannot validate documents: status is '{$currentStatus->value}', " .
            "but must be 'aprobado'"
        );
    }

    public static function cannotRegisterPayment(LoanStatusEnum $currentStatus): self
    {
        return new self(
            "Cannot register payment: loan status is '{$currentStatus->value}', " .
            "but must be 'activo'"
        );
    }
}
