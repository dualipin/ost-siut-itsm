<?php

declare(strict_types=1);

namespace App\Modules\Loan\Infrastructure\Service;

use App\Infrastructure\Config\AppConfig;
use App\Modules\Loan\Application\Service\PdfGeneratorInterface;
use App\Modules\Loan\Domain\Entity\Loan;
use App\Modules\Loan\Domain\Entity\Receipt;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final readonly class DompdfLoanPdfGenerator implements PdfGeneratorInterface
{
    public function __construct(private AppConfig $config)
    {
    }

    public function generatePromissoryNote(Loan $loan, array $userData): string
    {
        return $this->renderAndStore(
            'Pagare',
            'pagare',
            [
                'Prestamo: ' . (string) ($loan->loanId() ?? 0),
                'Usuario: ' . (string) ($userData['name'] ?? 'N/D'),
                'Monto aprobado: ' . ($loan->approvedAmount()?->format() ?? 'N/D'),
            ],
        );
    }

    public function generateConsentForm(Loan $loan, array $userData): string
    {
        return $this->renderAndStore(
            'Anuencia de Descuento',
            'anuencia',
            [
                'Prestamo: ' . (string) ($loan->loanId() ?? 0),
                'Usuario: ' . (string) ($userData['name'] ?? 'N/D'),
                'CURP: ' . (string) ($userData['curp'] ?? 'N/D'),
            ],
        );
    }

    public function generateApplicationForm(Loan $loan, array $userData, array $paymentConfigs): string
    {
        $lines = [
            'Prestamo: ' . (string) ($loan->loanId() ?? 0),
            'Usuario: ' . (string) ($userData['name'] ?? 'N/D'),
            'Banco para deposito: ' . (trim((string) ($userData['bank_name'] ?? '')) !== '' ? (string) $userData['bank_name'] : 'N/D'),
            'CLABE interbancaria: ' . (trim((string) ($userData['interbank_code'] ?? '')) !== '' ? (string) $userData['interbank_code'] : 'N/D'),
            'Numero de cuenta: ' . (trim((string) ($userData['bank_account'] ?? '')) !== '' ? (string) $userData['bank_account'] : 'N/D'),
            'Configuraciones de pago: ' . (string) count($paymentConfigs),
        ];

        foreach ($paymentConfigs as $index => $paymentConfig) {
            $lines[] = sprintf(
                'Forma de pago %d: %s | metodo: %s | total: $%0.2f | parcialidades: %d',
                $index + 1,
                (string) ($paymentConfig['income_type_name'] ?? ('Ingreso #' . (string) ($paymentConfig['income_type_id'] ?? 'N/D'))),
                (string) ($paymentConfig['interest_method'] ?? 'simple_aleman'),
                (float) ($paymentConfig['total_amount_to_deduct'] ?? 0),
                (int) ($paymentConfig['number_of_installments'] ?? 1)
            );
        }

        return $this->renderAndStore(
            'Formato de Solicitud',
            'solicitud',
            $lines,
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