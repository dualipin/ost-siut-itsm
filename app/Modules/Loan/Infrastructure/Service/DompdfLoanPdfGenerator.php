<?php

declare(strict_types=1);

namespace App\Modules\Loan\Infrastructure\Service;

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Loan\Application\Service\PdfGeneratorInterface;
use App\Modules\Loan\Domain\Entity\Loan;
use App\Modules\Loan\Domain\Entity\Receipt;
use DateTimeInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final readonly class DompdfLoanPdfGenerator implements PdfGeneratorInterface
{
    public function __construct(
        private AppConfig $config,
        private RendererInterface $renderer,
        private string $basePath,
    )
    {
    }

    public function generatePromissoryNote(Loan $loan, array $userData): string
    {
        return $this->renderTemplateAndStore(
            $this->basePath . '/templates/prestamos/formatos/pagare.latte',
            'pagare',
            $this->buildTemplateData($loan, $userData),
        );
    }

    public function generateConsentForm(Loan $loan, array $userData): string
    {
        return $this->renderTemplateAndStore(
            $this->basePath . '/templates/prestamos/formatos/descuento-agremiado.latte',
            'anuencia',
            $this->buildTemplateData($loan, $userData),
        );
    }

    public function generateApplicationForm(Loan $loan, array $userData, array $paymentConfigs): string
    {
        return $this->renderTemplateAndStore(
            $this->basePath . '/templates/prestamos/formatos/solicitud-prestamo.latte',
            'solicitud',
            $this->buildTemplateData($loan, $userData, $paymentConfigs),
        );
    }

    public function generateAmortizationSchedule(Loan $loan, array $userData, array $amortizationRows): string
    {
        return $this->renderAndStore(
            'Corrida Financiera',
            'corrida',
            [
                'Prestamo: ' . (string) ($loan->loanId() ?? 0),
                'Usuario: ' . (string) ($userData['name'] ?? 'N/D'),
                'Periodos: ' . (string) count($amortizationRows),
            ],
        );
    }

    public function generateAccountStatement(array $userData, array $loans): string
    {
        return $this->renderAndStore(
            'Estado de Cuenta',
            'estado_cuenta',
            [
                'Usuario: ' . (string) ($userData['name'] ?? 'N/D'),
                'Prestamos incluidos: ' . (string) count($loans),
                'Fecha de generacion: ' . date('Y-m-d H:i:s'),
            ],
        );
    }

    public function generateReceipt(Receipt $receipt, Loan $loan, array $userData): string
    {
        return $this->renderAndStore(
            'Comprobante de Pago',
            'comprobante',
            [
                'Folio: ' . $receipt->receiptFolio(),
                'Prestamo: ' . (string) ($loan->loanId() ?? 0),
                'Usuario: ' . (string) ($userData['name'] ?? 'N/D'),
                'Monto: ' . $receipt->amount()->format(),
            ],
        );
    }

    private function buildTemplateData(Loan $loan, array $userData, array $paymentConfigs = []): array
    {
        $approvedAmount = $loan->approvedAmount()?->amount() ?? $loan->requestedAmount()->amount();
        $requestedAmount = $loan->requestedAmount()->amount();
        $approvalDate = $loan->approvalDate() ?? $loan->applicationDate();
        $firstPaymentDate = $loan->firstPaymentDate();
        $lastPaymentDate = $loan->lastScheduledPaymentDate();
        $termFortnights = max(1, (int) ($loan->termFortnights() ?? 0));
        $interestRate = $loan->appliedInterestRate()->annual();

        $normalizedConfigs = array_map(
            static function (array $paymentConfig): array {
                $totalToDeduct = (float) ($paymentConfig['total_amount_to_deduct'] ?? 0.0);
                $installments = max(1, (int) ($paymentConfig['number_of_installments'] ?? 1));
                $installmentAmount = (float) ($paymentConfig['amount_per_installment'] ?? 0.0);
                $incomeTypeName = trim((string) ($paymentConfig['income_type_name'] ?? ''));

                return [
                    'income_type_name' => $incomeTypeName !== ''
                        ? $incomeTypeName
                        : 'Ingreso #' . (string) ($paymentConfig['income_type_id'] ?? 'N/D'),
                    'interest_method' => (string) ($paymentConfig['interest_method'] ?? 'simple_aleman'),
                    'total_amount_to_deduct' => $totalToDeduct,
                    'number_of_installments' => $installments,
                    'amount_per_installment' => $installmentAmount,
                    'document_status' => (string) ($paymentConfig['document_status'] ?? 'pendiente'),
                    'total_amount_label' => '$' . number_format($totalToDeduct, 2),
                    'installment_amount_label' => '$' . number_format($installmentAmount, 2),
                ];
            },
            $paymentConfigs,
        );

        $primaryConfig = $normalizedConfigs[0] ?? null;

        $installmentAmount = (float) ($primaryConfig['amount_per_installment'] ?? 0.0);
        if ($installmentAmount <= 0.0) {
            $installmentAmount = $approvedAmount / $termFortnights;
        }

        $borrowerName = trim((string) ($userData['name'] ?? ''));
        $borrowerCategory = trim((string) ($userData['category'] ?? ''));
        $borrowerPhone = trim((string) ($userData['phone'] ?? ''));
        $borrowerBankName = trim((string) ($userData['bank_name'] ?? ''));
        $borrowerInterbankCode = trim((string) ($userData['interbank_code'] ?? ''));
        $borrowerBankAccount = trim((string) ($userData['bank_account'] ?? ''));
        $borrowerDepartment = trim((string) ($userData['department'] ?? ''));

        return [
            'loan_id' => (int) ($loan->loanId() ?? 0),
            'folio' => (string) ($loan->folio()?->toString() ?? ''),
            'requested_amount' => $requestedAmount,
            'approved_amount' => $approvedAmount,
            'approved_amount_label' => '$' . number_format($approvedAmount, 2),
            'requested_amount_label' => '$' . number_format($requestedAmount, 2),
            'estimated_total_label' => $loan->estimatedTotalToPay()?->format() ?? '$0.00',
            'interest_rate' => $interestRate,
            'interest_rate_label' => number_format($interestRate, 2) . '%',
            'term_fortnights' => $termFortnights,
            'installment_amount_label' => '$' . number_format($installmentAmount, 2),
            'approval_date' => $this->formatDate($approvalDate, 'Y-m-d'),
            'approval_date_long' => $this->formatDate($approvalDate, 'd \\d\\e F \\d\\e\\l Y'),
            'application_date' => $this->formatDate($loan->applicationDate(), 'Y-m-d'),
            'first_payment_date' => $this->formatDate($firstPaymentDate, 'Y-m-d'),
            'last_payment_date' => $this->formatDate($lastPaymentDate, 'Y-m-d'),
            'borrower_name' => $borrowerName !== '' ? $borrowerName : 'N/D',
            'borrower_curp' => trim((string) ($userData['curp'] ?? '')) !== '' ? (string) $userData['curp'] : 'N/D',
            'borrower_category' => $borrowerCategory !== '' ? $borrowerCategory : 'N/D',
            'borrower_phone' => $borrowerPhone !== '' ? $borrowerPhone : 'N/D',
            'borrower_bank_name' => $borrowerBankName !== '' ? $borrowerBankName : 'N/D',
            'borrower_interbank_code' => $borrowerInterbankCode !== '' ? $borrowerInterbankCode : 'N/D',
            'borrower_bank_account' => $borrowerBankAccount !== '' ? $borrowerBankAccount : 'N/D',
            'borrower_address' => $borrowerDepartment !== '' ? $borrowerDepartment : 'N/D',
            'payment_configs' => $normalizedConfigs,
            'payment_config_count' => count($normalizedConfigs),
        ];
    }

    private function formatDate(?DateTimeInterface $date, string $format): string
    {
        if ($date === null) {
            return 'N/D';
        }

        return $date->format($format);
    }

    private function renderTemplateAndStore(string $templatePath, string $prefix, array $params): string
    {
        $html = $this->renderer->renderToString($templatePath, $params);

        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setIsHtml5ParserEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('Letter');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $path = $this->buildTargetPath($prefix);
        $bytes = file_put_contents($path, $dompdf->output());

        if ($bytes === false) {
            throw new RuntimeException('No fue posible guardar el PDF generado.');
        }

        return $path;
    }

    private function renderAndStore(string $title, string $prefix, array $lines): string
    {
        $htmlLines = array_map(static fn(string $line): string => '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>', $lines);

        $html = '<html><body>'
            . '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<ul>' . implode('', $htmlLines) . '</ul>'
            . '</body></html>';

        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('Letter');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $path = $this->buildTargetPath($prefix);
        $bytes = file_put_contents($path, $dompdf->output());

        if ($bytes === false) {
            throw new RuntimeException('No fue posible guardar el PDF generado.');
        }

        return $path;
    }

    private function buildTargetPath(string $prefix): string
    {
        $directory = rtrim($this->config->upload->privateDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'loans'
            . DIRECTORY_SEPARATOR
            . 'documents';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio para PDF de prestamos.');
        }

        $filename = sprintf('%s_%s_%s.pdf', $prefix, date('Ymd_His'), bin2hex(random_bytes(4)));

        return $directory . DIRECTORY_SEPARATOR . $filename;
    }
}