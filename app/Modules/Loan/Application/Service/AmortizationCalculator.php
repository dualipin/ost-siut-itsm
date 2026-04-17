<?php

namespace App\Modules\Loan\Application\Service;

use App\Modules\Loan\Domain\Entity\AmortizationRow;
use App\Modules\Loan\Domain\Entity\Loan;
use App\Modules\Loan\Domain\Enum\PaymentStatusEnum;
use App\Modules\Loan\Domain\ValueObject\InterestRate;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateInterval;
use DateTimeImmutable;

final readonly class AmortizationCalculator
{
    /**
     * Calculate German Simple Interest amortization schedule (método alemán)
     * - Constant principal payment per period
     * - Interest calculated on declining balance
     * - Payment dates: 15th or last day of month
     * 
     * @return AmortizationRow[]
     */
    public function calculateGermanSimple(
        Money $amount,
        InterestRate $rate,
        int $fortnights,
        DateTimeImmutable $disbursementDate,
        int $incomeTypeId = 1 // Default to nomina
    ): array {
        if ($fortnights <= 0) {
            return [];
        }

        $rows = [];
        $balance = $amount->amount();
        $principalPerPeriod = $amount->amount() / $fortnights;
        
        // Round principal per period to 2 decimals for consistency
        $roundedPrincipal = round($principalPerPeriod * 100) / 100;

        // Calculate first payment date (day 15 or last day of month)
        $firstPaymentDate = $this->calculateNextFortnightDate($disbursementDate);
        
        // Calculate additional days from disbursement to first payment
        $additionalDays = $this->calculateDaysBetween($disbursementDate, $firstPaymentDate);
        
        for ($i = 1; $i <= $fortnights; $i++) {
            // Calculate payment date
            $paymentDate = $this->calculateFortnightDate($firstPaymentDate, $i - 1);
            
            // Calculate interest
            if ($i === 1 && $additionalDays > 0) {
                // First period may have additional days
                $interest = $balance * $rate->daily() * $additionalDays / 100;
            } else {
                // Regular fortnightly interest
                $interest = $balance * $rate->fortnightly() / 100;
            }
            
            // Round interest to 2 decimals
            $interest = round($interest * 100) / 100;
            
            // Calculate principal (last payment adjusts for rounding)
            if ($i === $fortnights) {
                // Last payment: principal is what remains to zero out the balance
                $principal = round($balance * 100) / 100;
            } else {
                // Regular payments: use rounded principal
                $principal = $roundedPrincipal;
            }
            
            $totalPayment = $principal + $interest;
            // Ensure balance is properly rounded to avoid accumulation of floating point errors
            $newBalance = round(($balance - $principal) * 100) / 100;
            $newBalance = max(0, $newBalance);
            
            $rows[] = new AmortizationRow(
                amortizationId: null,
                loanId: 0, // Will be set when saved
                paymentNumber: $i,
                incomeTypeId: $incomeTypeId,
                scheduledDate: $paymentDate,
                initialBalance: Money::fromFloat($balance),
                principal: Money::fromFloat($principal),
                ordinaryInterest: Money::fromFloat($interest),
                totalScheduledPayment: Money::fromFloat($totalPayment),
                finalBalance: Money::fromFloat($newBalance),
                paymentStatus: PaymentStatusEnum::Pending,
                actualPaymentDate: null,
                actualPaidAmount: Money::zero(),
                daysOverdue: 0,
                generatedDefaultInterest: Money::zero(),
                paidBy: null,
                paymentReceipt: null,
                tableVersion: 1,
                active: true
            );
            
            $balance = $newBalance;
        }
        
        return $rows;
    }

    /**
     * Calculate compound interest for single payment (prestaciones)
     */
    public function calculateCompound(
        Money $amount,
        InterestRate $rate,
        DateTimeImmutable $disbursementDate,
        DateTimeImmutable $paymentDate
    ): Money {
        $days = $this->calculateDaysBetween($disbursementDate, $paymentDate);
        $totalAmount = $amount->amount() * pow(1 + ($rate->daily() / 100), $days);
        
        return Money::fromFloat($totalAmount);
    }

    /**
     * Build final amortization rows using each payment configuration.
     * - simple_aleman: fortnightly rows
     * - compuesto: single row at benefit payment date
     *
     * @param array<int, array<string, mixed>> $paymentConfigurations
     * @return AmortizationRow[]
     */
    public function calculateByPaymentConfigurations(
        Money $amount,
        InterestRate $rate,
        DateTimeImmutable $validationDate,
        array $paymentConfigurations
    ): array {
        if ($amount->isNegativeOrZero() || $paymentConfigurations === []) {
            return [];
        }

        $rows = [];
        $runningBalance = $amount->amount();
        $paymentNumber = 1;

        foreach ($paymentConfigurations as $config) {
            $configAmount = (float) ($config['total_amount_to_deduct'] ?? 0);
            if ($configAmount <= 0) {
                continue;
            }

            $incomeTypeId = (int) ($config['income_type_id'] ?? 1);
            $interestMethod = (string) ($config['interest_method'] ?? 'simple_aleman');

            if ($interestMethod === 'compuesto') {
                $paymentDate = $this->resolveBenefitPaymentDate($validationDate, $config);
                $totalAmount = $this->calculateCompound(
                    Money::fromFloat($configAmount),
                    $rate,
                    $validationDate,
                    $paymentDate
                )->amount();

                $interest = max(0, $totalAmount - $configAmount);
                $endingBalance = max(0, $runningBalance - $configAmount);

                $rows[] = new AmortizationRow(
                    amortizationId: null,
                    loanId: 0,
                    paymentNumber: $paymentNumber,
                    incomeTypeId: $incomeTypeId,
                    scheduledDate: $paymentDate,
                    initialBalance: Money::fromFloat($runningBalance),
                    principal: Money::fromFloat($configAmount),
                    ordinaryInterest: Money::fromFloat($interest),
                    totalScheduledPayment: Money::fromFloat($totalAmount),
                    finalBalance: Money::fromFloat($endingBalance),
                    paymentStatus: PaymentStatusEnum::Pending,
                    actualPaymentDate: null,
                    actualPaidAmount: Money::zero(),
                    daysOverdue: 0,
                    generatedDefaultInterest: Money::zero(),
                    paidBy: null,
                    paymentReceipt: null,
                    tableVersion: 1,
                    active: true
                );

                $runningBalance = $endingBalance;
                $paymentNumber++;
                continue;
            }

            $installments = max(1, (int) ($config['number_of_installments'] ?? 1));
            $simpleRows = $this->calculateGermanSimple(
                Money::fromFloat($configAmount),
                $rate,
                $installments,
                $validationDate,
                $incomeTypeId
            );

            foreach ($simpleRows as $simpleRow) {
                $principal = min($runningBalance, $simpleRow->principal()->amount());
                $interest = $simpleRow->ordinaryInterest()->amount();
                $total = $principal + $interest;
                $endingBalance = max(0, $runningBalance - $principal);

                $rows[] = new AmortizationRow(
                    amortizationId: null,
                    loanId: 0,
                    paymentNumber: $paymentNumber,
                    incomeTypeId: $simpleRow->incomeTypeId(),
                    scheduledDate: $simpleRow->scheduledDate(),
                    initialBalance: Money::fromFloat($runningBalance),
                    principal: Money::fromFloat($principal),
                    ordinaryInterest: Money::fromFloat($interest),
                    totalScheduledPayment: Money::fromFloat($total),
                    finalBalance: Money::fromFloat($endingBalance),
                    paymentStatus: PaymentStatusEnum::Pending,
                    actualPaymentDate: null,
                    actualPaidAmount: Money::zero(),
                    daysOverdue: 0,
                    generatedDefaultInterest: Money::zero(),
                    paidBy: null,
                    paymentReceipt: null,
                    tableVersion: 1,
                    active: true
                );

                $runningBalance = $endingBalance;
                $paymentNumber++;
            }
        }

        return $rows;
    }

    /**
     * Calculate default interest (interés moratorio)
     */
    public function calculateDefaultInterest(
        Money $balance,
        float $dailyRate,
        int $daysOverdue
    ): Money {
        $defaultInterest = $balance->amount() * $dailyRate * $daysOverdue;
        return Money::fromFloat($defaultInterest);
    }

    /**
     * Regenerate amortization table after pico or extraordinary payment
     */
    public function regenerateAmortizationTable(
        Loan $loan,
        Money $newBalance,
        int $newVersion,
        int $startingPaymentNumber,
        DateTimeImmutable $startDate,
        int $remainingFortnights
    ): array {
        // Calculate new amortization schedule from the current point
        return $this->calculateGermanSimple(
            $newBalance,
            $loan->appliedInterestRate(),
            $remainingFortnights,
            $startDate
        );
    }

    /**
     * Calculate last payment date for validation (must be before Dec 31)
     */
    public function calculateLastPaymentDate(
        DateTimeImmutable $startDate,
        int $fortnights
    ): DateTimeImmutable {
        $firstPayment = $this->calculateNextFortnightDate($startDate);
        return $this->calculateFortnightDate($firstPayment, $fortnights - 1);
    }

    /**
     * Calculate next fortnight date (day 15 or last day of month)
     */
    private function calculateNextFortnightDate(DateTimeImmutable $date): DateTimeImmutable
    {
        $day = (int) $date->format('d');
        
        if ($day <= 15) {
            // Next payment is 15th of current month
            return DateTimeImmutable::createFromFormat('Y-m-d', $date->format('Y-m-15'));
        } else {
            // Next payment is last day of current month
            return new DateTimeImmutable($date->format('Y-m-t'));
        }
    }

    /**
     * Calculate fortnight date given base date and offset
     */
    private function calculateFortnightDate(DateTimeImmutable $baseDate, int $offset): DateTimeImmutable
    {
        if ($offset === 0) {
            return $baseDate;
        }
        
        $currentDate = $baseDate;
        
        for ($i = 0; $i < $offset; $i++) {
            $day = (int) $currentDate->format('d');
            
            if ($day === 15) {
                // From 15th, go to last day of month
                $currentDate = new DateTimeImmutable($currentDate->format('Y-m-t'));
            } else {
                // From last day of month, go to 15th of next month
                $currentDate = $currentDate->add(new DateInterval('P1M'));
                $currentDate = DateTimeImmutable::createFromFormat('Y-m-d', $currentDate->format('Y-m-15'));
            }
        }
        
        return $currentDate;
    }

    /**
     * Calculate days between two dates
     */
    private function calculateDaysBetween(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return (int) $start->diff($end)->days;
    }

    /**
     * Resolve the next payment date for a benefit (prestacion) config.
     * Falls back to validation date month/day when config has no tentative date.
     *
     * @param array<string, mixed> $config
     */
    private function resolveBenefitPaymentDate(DateTimeImmutable $validationDate, array $config): DateTimeImmutable
    {
        $month = (int) ($config['income_payment_month'] ?? $validationDate->format('n'));
        $day = (int) ($config['income_payment_day'] ?? $validationDate->format('d'));

        if ($month < 1 || $month > 12) {
            $month = (int) $validationDate->format('n');
        }

        $year = (int) $validationDate->format('Y');
        $baseDate = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $safeDay = max(1, min($day, (int) $baseDate->format('t')));
        $paymentDate = $baseDate->setDate($year, $month, $safeDay);

        if ($paymentDate < $validationDate) {
            $paymentDate = $paymentDate->add(new DateInterval('P1Y'));
        }

        return $paymentDate;
    }
}
