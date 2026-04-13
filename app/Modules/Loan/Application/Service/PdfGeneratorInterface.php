<?php

namespace App\Modules\Loan\Application\Service;

use App\Modules\Loan\Domain\Entity\Loan;
use App\Modules\Loan\Domain\Entity\Receipt;

interface PdfGeneratorInterface
{
    /**
     * Generate promissory note (pagaré)
     */
    public function generatePromissoryNote(Loan $loan, array $userData): string;

    /**
     * Generate consent form (anuencia de descuento)
     */
    public function generateConsentForm(Loan $loan, array $userData): string;

    /**
     * Generate application form (formato de solicitud)
     */
    public function generateApplicationForm(Loan $loan, array $userData, array $paymentConfigs): string;

    /**
     * Generate amortization schedule (corrida financiera)
     */
    public function generateAmortizationSchedule(Loan $loan, array $userData, array $amortizationRows): string;

    /**
     * Generate account statement (estado de cuenta)
     */
    public function generateAccountStatement(array $userData, array $loans): string;

    /**
     * Generate receipt (comprobante)
     */
    public function generateReceipt(Receipt $receipt, Loan $loan, array $userData): string;
}
