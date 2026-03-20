<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Domain\Exception\ReplyValidationException;
use Dompdf\Dompdf;
use Dompdf\Options;

final readonly class GenerateThreadPdfUseCase
{
    public function __construct(
        private GetThreadDetailUseCase $getThreadDetailUseCase,
        private RendererInterface $renderer,
    ) {}

    /**
     * @throws ReplyValidationException
     */
    public function execute(int $threadId): void
    {
        $data = $this->getThreadDetailUseCase->execute($threadId);
        
        $html = $this->renderer->renderToString(__DIR__ . '/../Templates/thread_pdf.latte', [
            'thread' => $data['thread'],
            'messages' => $data['messages'],
            'year' => (int)date('Y'),
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = "Duda_" . $threadId . "_" . date('Ymd') . ".pdf";
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    }
}
