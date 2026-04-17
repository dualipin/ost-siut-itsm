<?php
require "vendor/autoload.php";

use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Domain\ValueObject\Money;
use App\Modules\Loan\Domain\ValueObject\InterestRate;

$calculator = new AmortizationCalculator();

// Caso 1: Simple
$amount = 10000;
$rateValue = 7.5;
$terms = 4;
$dateStart = new DateTimeImmutable("2026-04-10");

$schedule = $calculator->calculateGermanSimple(
    new Money($amount),
    new InterestRate($rateValue / 100),
    $terms,
    $dateStart
);

$totalSimple = 0;
foreach ($schedule as $payment) {
    $totalSimple += $payment->totalScheduledPayment()->amount();
}
$firstInterest = $schedule[0]->ordinaryInterest()->amount();

// Formula simulador Case 1: interes1 = saldo*(0.075/2)+monto*(0.075/30*diasAdicionales)
$diasAdicionales = 15 - 10;
$interes1Sim = $amount * ((7.5/100) / 2) + $amount * ((7.5/100) / 30 * $diasAdicionales);

echo "--- Caso 1 (German Simple) ---\n";
echo "Total Pagado: $totalSimple\n";
echo "Primer Interes: $firstInterest\n";
echo "Interes 1 Simulado: $interes1Sim\n";
echo "Diferencia Interes 1: " . ($firstInterest - $interes1Sim) . "\n\n";


// Caso 2: Compuesto
$dateEnd = new DateTimeImmutable("2026-10-01");
$diff = $dateStart->diff($dateEnd);
$days = $diff->days;
$numQuincenas = ceil($days / 15);

// Formula simulador Case 2: tasaQ=pow(1+0.075,0.5)-1, total=monto*pow(1+tasaQ,numQuincenas)
$tasaQ = pow(1 + 0.075, 0.5) - 1;
$totalSim2 = $amount * pow(1 + $tasaQ, $numQuincenas);

// Corregimos firma calculateCompound(Money, InterestRate, DateTimeImmutable, DateTimeImmutable): Money
$totalCompoundObj = $calculator->calculateCompound(
    new Money($amount),
    new InterestRate($rateValue / 100),
    $dateStart,
    $dateEnd
);

$totalReal2 = $totalCompoundObj->amount();

echo "--- Caso 2 (Compound) ---\n";
echo "Dias: $days, Quincenas: $numQuincenas\n";
echo "Total Real: $totalReal2\n";
echo "Total Simulado: $totalSim2\n";
echo "Diferencia Total: " . ($totalReal2 - $totalSim2) . "\n";
?>
