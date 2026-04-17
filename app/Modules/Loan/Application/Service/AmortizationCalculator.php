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
    private const FIRST_PAYMENT_TOLERANCE_DAYS = 15;

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
        $monthlyRate = $rate->annual() / 100;
        $fortnightRate = $monthlyRate / 2;
        $dailyRate = $monthlyRate / 30;
        
        // Round principal per period to 2 decimals for consistency
        $roundedPrincipal = round($principalPerPeriod * 100) / 100;

        // First payment starts after tolerance and then aligns to the next fortnight boundary.
        $firstEligibleDate = $this->applyFirstPaymentTolerance($disbursementDate);
        $firstPaymentDate = $this->calculateNextFortnightDate($firstEligibleDate);

        // Keep existing simulator rule: one base fortnight plus extra days beyond 15.
        $elapsedDays = $this->calculateDaysBetween($disbursementDate, $firstPaymentDate);
        $additionalDays = max(0, $elapsedDays - self::FIRST_PAYMENT_TOLERANCE_DAYS);
        
        for ($i = 1; $i <= $fortnights; $i++) {
            // Calculate payment date
            $paymentDate = $this->calculateFortnightDate($firstPaymentDate, $i - 1);
            
            // Simulador rule: base fortnightly interest plus extra initial days.
            $interest = $balance * $fortnightRate;
            if ($i === 1 && $additionalDays > 0) {
                $interest += $amount->amount() * $dailyRate * $additionalDays;
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
        $days = max(0, $this->calculateDaysBetween($disbursementDate, $paymentDate));
        $fortnights = max(1, (int) ceil($days / 15));
        $fortnightCompoundRate = pow(1 + ($rate->annual() / 100), 0.5) - 1;
        $totalAmount = $amount->amount() * pow(1 + $fortnightCompoundRate, $fortnights);
        
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
        $generatedSegments = [];
        $sequence = 0;

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

                $generatedSegments[] = [
                    'order' => $sequence++,
                    'income_type_id' => $incomeTypeId,
                    'scheduled_date' => $paymentDate,
                    'principal' => $configAmount,
                    'interest' => $interest,
                    'total' => $totalAmount,
                ];

                continue;
            }

            $installments = max(1, (int) ($config['number_of_installments'] ?? 1));
            $isPeriodicIncome = (bool) ($config['income_is_periodic'] ?? false);
            $frequencyDays = max(1, (int) ($config['income_frequency_days'] ?? 15));

            $periodicDates = $isPeriodicIncome
                ? $this->buildPeriodicPaymentDates($validationDate, $config, $installments)
                : [];

            $simpleRows = $periodicDates !== []
                ? $this->calculateGermanSimpleByDates(
                    Money::fromFloat($configAmount),
                    $rate,
                    $validationDate,
                    $periodicDates,
                    $incomeTypeId,
                    $frequencyDays
                )
                : $this->calculateGermanSimple(
                    Money::fromFloat($configAmount),
                    $rate,
                    $installments,
                    $validationDate,
                    $incomeTypeId
                );

            foreach ($simpleRows as $simpleRow) {
                $generatedSegments[] = [
                    'order' => $sequence++,
                    'income_type_id' => $simpleRow->incomeTypeId(),
                    'scheduled_date' => $simpleRow->scheduledDate(),
                    'principal' => $simpleRow->principal()->amount(),
                    'interest' => $simpleRow->ordinaryInterest()->amount(),
                    'total' => $simpleRow->totalScheduledPayment()->amount(),
                ];
            }
        }

        usort(
            $generatedSegments,
            static function (array $a, array $b): int {
                /** @var DateTimeImmutable $dateA */
                $dateA = $a['scheduled_date'];
                /** @var DateTimeImmutable $dateB */
                $dateB = $b['scheduled_date'];

                $byDate = $dateA <=> $dateB;
                if ($byDate !== 0) {
                    return $byDate;
                }

                return ((int) $a['order']) <=> ((int) $b['order']);
            }
        );

        $segmentsWithPrincipal = array_values(
            array_filter(
                $generatedSegments,
                static fn (array $segment): bool => max(0.0, (float) $segment['principal']) > 0.0
            )
        );

        $segmentCount = count($segmentsWithPrincipal);

        foreach ($segmentsWithPrincipal as $index => $segment) {
            if ($runningBalance <= 0) {
                break;
            }

            $isLastSegment = $index === $segmentCount - 1;

            $principal = $isLastSegment
                ? round($runningBalance * 100) / 100
                : min($runningBalance, max(0.0, (float) $segment['principal']));

            $principal = round($principal * 100) / 100;

            if ($principal <= 0) {
                continue;
            }

            $interest = round(max(0.0, (float) $segment['interest']) * 100) / 100;
            $total = round(($principal + $interest) * 100) / 100;
            $endingBalance = max(0, round(($runningBalance - $principal) * 100) / 100);

            /** @var DateTimeImmutable $scheduledDate */
            $scheduledDate = $segment['scheduled_date'];

            $rows[] = new AmortizationRow(
                amortizationId: null,
                loanId: 0,
                paymentNumber: $paymentNumber,
                incomeTypeId: (int) $segment['income_type_id'],
                scheduledDate: $scheduledDate,
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
        $firstEligibleDate = $this->applyFirstPaymentTolerance($startDate);
        $firstPayment = $this->calculateNextFortnightDate($firstEligibleDate);
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
     * Build periodic payment dates using the same month-density rule as simulator.
     *
     * @param array<string, mixed> $config
     * @return DateTimeImmutable[]
     */
    private function buildPeriodicPaymentDates(
        DateTimeImmutable $startDate,
        array $config,
        int $installments
    ): array {
        if ($installments <= 0) {
            return [];
        }

        $firstEligibleDate = $this->applyFirstPaymentTolerance($startDate);
        $frequencyDays = max(1, (int) ($config['income_frequency_days'] ?? 30));
        $tentativeDay = max(1, (int) ($config['income_payment_day'] ?? 15));
        $limitDate = new DateTimeImmutable($firstEligibleDate->format('Y') . '-11-15');

        if ($firstEligibleDate > $limitDate) {
            return [];
        }

        $dates = [];
        $cursor = $firstEligibleDate->setDate((int) $firstEligibleDate->format('Y'), (int) $firstEligibleDate->format('m'), 1);
        $guard = 0;

        while ($cursor <= $limitDate && $guard < 36 && count($dates) < $installments) {
            $year = (int) $cursor->format('Y');
            $month = (int) $cursor->format('m');
            $daysInMonth = (int) $cursor->format('t');

            $monthlyFraction = $frequencyDays / $daysInMonth;
            $timesInMonth = $monthlyFraction > 0 ? max(1, (int) round(1 / $monthlyFraction)) : 1;
            $baseDay = min(max(1, $tentativeDay), $daysInMonth);
            $stepDays = max(1, (int) round($daysInMonth / $timesInMonth));

            for ($i = 0; $i < $timesInMonth && count($dates) < $installments; $i++) {
                $candidateDay = $baseDay + ($i * $stepDays);
                if ($candidateDay > $daysInMonth) {
                    break;
                }

                $paymentDate = $cursor->setDate($year, $month, $candidateDay);
                if ($paymentDate < $firstEligibleDate || $paymentDate > $limitDate) {
                    continue;
                }

                $dates[] = $paymentDate;
            }

            $cursor = $cursor->modify('first day of next month');
            $guard++;
        }

        return $dates;
    }

    private function applyFirstPaymentTolerance(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->add(new DateInterval('P' . self::FIRST_PAYMENT_TOLERANCE_DAYS . 'D'));
    }

    /**
     * Generate simple-interest German rows using explicit payment dates.
     *
     * @param DateTimeImmutable[] $scheduledDates
     * @return AmortizationRow[]
     */
    private function calculateGermanSimpleByDates(
        Money $amount,
        InterestRate $rate,
        DateTimeImmutable $disbursementDate,
        array $scheduledDates,
        int $incomeTypeId,
        int $fallbackFrequencyDays = 15
    ): array {
        if ($scheduledDates === []) {
            return [];
        }

        $rows = [];
        $balance = $amount->amount();
        $installments = count($scheduledDates);
        $principalPerPeriod = $installments > 0 ? ($amount->amount() / $installments) : 0.0;
        $roundedPrincipal = round($principalPerPeriod * 100) / 100;
        $periodRate = ($rate->annual() / 100) * (max(1, $fallbackFrequencyDays) / 30);

        foreach ($scheduledDates as $index => $paymentDate) {
            $interest = $balance * $periodRate;
            $interest = round($interest * 100) / 100;

            if ($index === $installments - 1) {
                $principal = round($balance * 100) / 100;
            } else {
                $principal = $roundedPrincipal;
            }

            $totalPayment = $principal + $interest;
            $newBalance = round(($balance - $principal) * 100) / 100;
            $newBalance = max(0, $newBalance);

            $rows[] = new AmortizationRow(
                amortizationId: null,
                loanId: 0,
                paymentNumber: $index + 1,
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

        if ($paymentDate <= $validationDate) {
            $paymentDate = $paymentDate->add(new DateInterval('P1Y'));
        }

        return $paymentDate;
    }
}
