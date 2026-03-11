<?php

use App\Servicios\ServicioLatte;

/**
 * @param string $template
 * @param array $resultados
 * @param array $documentos
 * @param string|null $error
 * @param string|null $exito
 * @param int $paginaActual
 * @param int $totalPaginas
 * @return void
 */
function respuestaDocumento(
        string $template,
        array $resultados,
        array $documentos,
        ?string $error,
        ?string $exito,
        int $paginaActual,
        int $totalPaginas
): void {
    foreach ($resultados as $fila) {
        $documentos[] = [
                'id' => (int)$fila['id'],
                'titulo' => $fila['titulo'],
                'fecha_subida' => $fila['fecha_subida'],
                'privado' => (bool)$fila['privado']
        ];
    }

    $data = [
            'error' => $error,
            'mensajeExito' => $exito,
            'documentos' => $documentos,
            'paginaActual' => $paginaActual,
            'totalPaginas' => $totalPaginas
    ];

    ServicioLatte::renderizar($template, $data);
}
