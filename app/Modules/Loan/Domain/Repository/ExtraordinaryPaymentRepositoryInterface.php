<?php

namespace App\Modules\Loan\Domain\Repository;

use App\Modules\Loan\Domain\Entity\ExtraordinaryPayment;

interface ExtraordinaryPaymentRepositoryInterface
{
    public function findById(int $paymentId): ?ExtraordinaryPayment;

    public function findByLoanId(int $loanId): array;

    public function save(ExtraordinaryPayment $payment): int;
}
