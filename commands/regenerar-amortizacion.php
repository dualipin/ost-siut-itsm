<?php

/**
 * Regenerate amortization table for a specific loan
 * Usage: php commands/regenerar-amortizacion.php <loan_id>
 */

use App\Bootstrap;
use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\AmortizationRepositoryInterface;
use App\Modules\Loan\Domain\Repository\PaymentConfigRepositoryInterface;

require_once __DIR__ . "/../bootstrap.php";

// Validate argument
if ($argc < 2) {
    echo "Uso: php regenerar-amortizacion.php <loan_id>\n";
    exit(1);
}

$loanId = (int) $argv[1];

if ($loanId <= 0) {
    echo "Error: ID del préstamo inválido\n";
    exit(1);
}

$container = Bootstrap::buildContainer();
$loanRepository = $container->get(LoanRepositoryInterface::class);
$amortizationRepository = $container->get(AmortizationRepositoryInterface::class);
$paymentConfigRepository = $container->get(PaymentConfigRepositoryInterface::class);
$amortizationCalculator = $container->get(AmortizationCalculator::class);

// Load the loan
$loan = $loanRepository->findById($loanId);

if ($loan === null) {
    echo "Error: Préstamo con ID {$loanId} no encontrado\n";
    exit(1);
}

if ($loan->approvedAmount() === null) {
    echo "Error: El préstamo no ha sido aprobado\n";
    exit(1);
}

echo "Regenerando tabla de amortización para préstamo ID {$loanId}...\n";

// Get payment configurations
$paymentConfigurations = $paymentConfigRepository->findByLoanIdWithIncomeType($loanId);

if (empty($paymentConfigurations)) {
    echo "Advertencia: No se encontraron configuraciones de pago para este préstamo\n";
    echo "Usando método de cálculo simple alemán...\n";
    
    // Fall back to simple German method
    $newAmortization = $amortizationCalculator->calculateGermanSimple(
        $loan->approvedAmount(),
        $loan->appliedInterestRate(),
        $loan->termFortnights() ?? 12,
        new DateTimeImmutable()
    );
} else {
    // Use payment configuration method
    $newAmortization = $amortizationCalculator->calculateByPaymentConfigurations(
        $loan->approvedAmount(),
        $loan->appliedInterestRate(),
        new DateTimeImmutable(),
        $paymentConfigurations
    );
}

// Assign loan ID to each row and set to table_version 1
$rowsForUpdate = [];
foreach ($newAmortization as $row) {
    $rowsForUpdate[] = new \App\Modules\Loan\Domain\Entity\AmortizationRow(
        amortizationId: null,
        loanId: $loanId,
        paymentNumber: $row->paymentNumber(),
        incomeTypeId: $row->incomeTypeId(),
        scheduledDate: $row->scheduledDate(),
        initialBalance: $row->initialBalance(),
        principal: $row->principal(),
        ordinaryInterest: $row->ordinaryInterest(),
        totalScheduledPayment: $row->totalScheduledPayment(),
        finalBalance: $row->finalBalance(),
        paymentStatus: $row->paymentStatus(),
        actualPaymentDate: null,
        actualPaidAmount: $row->actualPaidAmount(),
        daysOverdue: $row->daysOverdue(),
        generatedDefaultInterest: $row->generatedDefaultInterest(),
        paidBy: null,
        paymentReceipt: null,
        tableVersion: 1,
        active: true
    );
}

// Delete old amortization rows
$amortizationRepository->deactivateByLoanId($loanId);

// Save new amortization rows
$amortizationRepository->saveAll($rowsForUpdate);

echo "\n✓ Tabla de amortización regenerada exitosamente\n";
echo "Total de cuotas: " . count($rowsForUpdate) . "\n";

if (!empty($rowsForUpdate)) {
    $firstRow = $rowsForUpdate[0];
    $lastRow = end($rowsForUpdate);
    
    echo "Primera cuota: " . $firstRow->scheduledDate()->format('d/m/Y') . 
         " - Capital: $" . number_format($firstRow->principal()->amount(), 2, ',', '.') . 
         " - Interés: $" . number_format($firstRow->ordinaryInterest()->amount(), 2, ',', '.') . "\n";
    
    echo "Última cuota: " . $lastRow->scheduledDate()->format('d/m/Y') . 
         " - Capital: $" . number_format($lastRow->principal()->amount(), 2, ',', '.') . 
         " - Interés: $" . number_format($lastRow->ordinaryInterest()->amount(), 2, ',', '.') . "\n";
    
    $totalCapital = array_reduce($rowsForUpdate, fn($sum, $row) => $sum + $row->principal()->amount(), 0);
    $totalInterest = array_reduce($rowsForUpdate, fn($sum, $row) => $sum + $row->ordinaryInterest()->amount(), 0);
    $totalPayment = array_reduce($rowsForUpdate, fn($sum, $row) => $sum + $row->totalScheduledPayment()->amount(), 0);
    
    echo "\nTotales:\n";
    echo "  Capital: $" . number_format($totalCapital, 2, ',', '.') . "\n";
    echo "  Interés: $" . number_format($totalInterest, 2, ',', '.') . "\n";
    echo "  Pago total: $" . number_format($totalPayment, 2, ',', '.') . "\n";
}

exit(0);
