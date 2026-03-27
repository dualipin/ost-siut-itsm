<?php

declare(strict_types=1);

namespace App\Modules\Requests\Application\UseCase;

use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Requests\Domain\Exception\RequestNotFoundException;
use App\Modules\Requests\Infrastructure\Persistence\PdoRequestRepository;
use Dompdf\Dompdf;
use Dompdf\Options;

final readonly class GenerateRequestPdfUseCase
{
    public function __construct(
        private GetRequestDetailUseCase $getRequestDetailUseCase,
        private PdoRequestRepository $repository,
        private RendererInterface $renderer,
    ) {}

    /**
     * @throws RequestNotFoundException
     * @throws \Throwable
     */
    public function execute(int $requestId): void
    {
        $data = $this->getRequestDetailUseCase->execute($requestId);
        
        $request = $data['request'];
        $history = $this->repository->findStatusHistory($requestId);

        $html = $this->renderer->renderToString(__DIR__ . '/../../../../../templates/solicitudes/detalle_pdf.latte', [
            'request' => $request,
            'history' => $history,
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $folio = $request->folio ?? $request->request_id ?? $requestId;
        $filename = "Solicitud_" . $folio . "_" . date('Ymd') . ".pdf";

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    }
}
