<?php

declare(strict_types=1);

namespace App\Http\Actions\Loan;

use App\Http\Actions\Action;
use App\Modules\Loan\Application\UseCase\LoanSimulatorUseCase;
use App\Modules\Loan\Domain\DTO\LoanSimulationDTO;
use App\Modules\Loan\Domain\DTO\LoanSimulationDiscountDTO;
use App\Infrastructure\Templating\RendererInterface;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Override;

class LoanSimulateAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private LoanSimulatorUseCase $loanSimulatorUseCase,
        private Dompdf $dompdf,
        private RendererInterface $renderer
    ) {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): ResponseInterface
    {
        $body = (array) ($this->request->getParsedBody() ?? []);

        $descuentos = array_map(
            static fn(array $desc): LoanSimulationDiscountDTO => new LoanSimulationDiscountDTO(
                monto: (float) ($desc['monto'] ?? 0.0),
                tipoId: (int) ($desc['tipoId'] ?? 0),
                cantidad: (int) ($desc['cantidad'] ?? 1),
                fechaPago: isset($desc['fechaPago']) ? (string)$desc['fechaPago'] : null,
            ),
            (array) ($body['descuentos'] ?? [])
        );

        $dto = new LoanSimulationDTO(
            montoPrestamo: (float) ($body['montoPrestamo'] ?? 0.0),
            fechaOtorgamiento: (string) ($body['fechaOtorgamiento'] ?? date('Y-m-d')),
            mesesPagar: (int) ($body['mesesPagar'] ?? 0),
            diasAdicionales: (int) ($body['diasAdicionales'] ?? 0),
            tasaInteres: (float) ($body['tasaInteresMensual'] ?? 0.0),
            descuentos: $descuentos,
        );

        $result = $this->loanSimulatorUseCase->execute($dto);

        $output = (string) ($body['output'] ?? 'json');

        if ($output === 'pdf') {
            $options = $this->dompdf->getOptions();
            $options->setIsRemoteEnabled(true);
            $options->setIsHtml5ParserEnabled(true);
            $this->dompdf->setOptions($options);

            $html = $this->renderer->renderToString(
                __DIR__ . '/../../../../templates/prestamos/pdf-simulados.latte',
                [
                    'montoPrestamo' => $result['montoPrestamo'],
                    'mesesPagar' => $result['mesesPagar'],
                    'diasAdicionales' => $result['diasAdicionales'],
                    'tasaInteresMensual' => $result['tasaInteresMensual'],
                    'fechaOtorgamiento' => $result['fechaOtorgamiento'],
                    'formasPago' => $result['formasPago'],
                    'resumenAnual' => $result['resumenAnual'],
                    'corridaPrestaciones' => $result['corridaPrestaciones'],
                    'corridasPorTipo' => $result['corridasPorTipo'],
                    'resumen' => $result['resumen'],
                    'fecha_simulacion' => (new DateTimeImmutable())->format('d/m/Y H:i'),
                ]
            );

            $this->dompdf->loadHtml($html);
            $this->dompdf->render();
            $pdfOutput = $this->dompdf->output();

            $this->response->getBody()->write($pdfOutput);

            return $this->response
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', 'attachment; filename="simulacion.pdf"');
        }

        return $this->respondWithData($result);
    }
}

