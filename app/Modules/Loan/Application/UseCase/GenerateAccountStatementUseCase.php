<?php

declare(strict_types=1);

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\AmortizationRepositoryInterface;
use App\Modules\Loan\Domain\Repository\ExtraordinaryPaymentRepositoryInterface;
use App\Modules\Loan\Domain\Repository\ReceiptRepositoryInterface;
use App\Modules\Loan\Domain\Exception\LoanNotFoundException;
use App\Modules\Loan\Application\Service\PdfGeneratorInterface;
use App\Modules\Loan\Application\Service\LoanEventLogger;

/**
 * Use Case: Generate Account Statement (Estado de Cuenta)
 * 
 * Business Flow:
 * 1. Gather loan details
 * 2. Get all amortization rows with payment status
 * 3. Get all extraordinary payments
 * 4. Get all receipts
 * 5. Calculate totals and balances
 * 6. Generate PDF document
 * 7. Log event
 */
final readonly class GenerateAccountStatementUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private AmortizationRepositoryInterface $amortizationRepository,
        private ExtraordinaryPaymentRepositoryInterface $extraordinaryPaymentRepository,
        private ReceiptRepositoryInterface $receiptRepository,
        private PdfGeneratorInterface $pdfGenerator,
        private LoanEventLogger $eventLogger
    ) {
    }

    /**
     * @param array{
     *   loan_id: int,
     *   generated_by: int,
     *   statement_date?: string,
     *   include_future_payments?: bool
     * } $data
     * @throws LoanNotFoundException
     * @return array{file_path: string, file_name: string}
     */
    public function execute(array $data): array
    {
        $loan = $this->loanRepository->findById($data['loan_id']);
        if (!$loan) {
            throw new LoanNotFoundException("Loan with ID {$data['loan_id']} not found");
        }

        $statementDate = $data['statement_date'] ?? date('Y-m-d');
        $includeFuturePayments = $data['include_future_payments'] ?? true;

        // Get all amortization rows
        $allRows = $this->amortizationRepository->findByLoanId($loan->id);
        
        // Filter based on statement date and preference
        $rows = array_filter($allRows, function($row) use ($statementDate, $includeFuturePayments) {
            if (!$includeFuturePayments && $row->paymentDate > $statementDate) {
                return false;
            }
            return true;
        });

        // Get extraordinary payments
        $extraordinaryPayments = $this->extraordinaryPaymentRepository->findByLoanId($loan->id);
        
        // Filter extraordinary payments by statement date
        $extraordinaryPayments = array_filter($extraordinaryPayments, function($payment) use ($statementDate) {
            return $payment->paymentDate <= $statementDate;
        });

        // Get receipts
        $receipts = $this->receiptRepository->findByLoanId($loan->id);
        
        // Filter receipts by statement date
        $receipts = array_filter($receipts, function($receipt) use ($statementDate) {
            return $receipt->paymentDate <= $statementDate;
        });

        // Calculate totals
        $totalPaid = 0;
        $totalPrincipalPaid = 0;
        $totalInterestPaid = 0;
        $totalPending = 0;

        foreach ($allRows as $row) {
            if ($row->paymentStatus->value === 'pagado' && $row->paidAt <= $statementDate) {
                $totalPaid += $row->totalPayment;
                $totalPrincipalPaid += $row->principal;
                $totalInterestPaid += $row->interest;
            } elseif ($row->paymentStatus->value === 'pendiente') {
                $totalPending += $row->totalPayment;
            }
        }

        // Add extraordinary payments
        $totalExtraordinaryPrincipal = array_sum(
            array_map(fn($p) => $p->appliedToPrincipal, $extraordinaryPayments)
        );
        $totalExtraordinaryInterest = array_sum(
            array_map(fn($p) => $p->appliedToInterest, $extraordinaryPayments)
        );

        // Prepare data for PDF
        $statementData = [
            'loan' => $loan,
            'statement_date' => $statementDate,
            'amortization_rows' => $rows,
            'extraordinary_payments' => $extraordinaryPayments,
            'receipts' => $receipts,
            'totals' => [
                'total_paid' => $totalPaid,
                'total_principal_paid' => $totalPrincipalPaid,
                'total_interest_paid' => $totalInterestPaid,
                'total_extraordinary_principal' => $totalExtraordinaryPrincipal,
                'total_extraordinary_interest' => $totalExtraordinaryInterest,
                'total_pending' => $totalPending,
                'current_balance' => $loan->outstandingBalance,
            ],
            'generated_date' => date('Y-m-d H:i:s'),
            'generated_by' => $data['generated_by'],
        ];

        // Generate PDF
        $result = $this->pdfGenerator->generateAccountStatement($statementData);

        // Log event
        $this->eventLogger->log(
            $loan->id,
            'account_statement_generated',
            $data['generated_by'],
            [
                'statement_date' => $statementDate,
                'file_path' => $result['file_path'],
                'total_paid' => $totalPaid,
                'current_balance' => $loan->outstandingBalance,
            ]
        );

        return $result;
    }
}
