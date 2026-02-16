<?php

require_once __DIR__ . '/../../src/configuracion.php';

$pdo = \App\Configuracion\MysqlConexion::conexion();

$filtrar = false;
$eventoFiltrado = '';
$query = "SELECT * FROM participantes";

// Verificar si se está filtrando por evento
if (isset($_GET['filtrar']) && !empty($_GET['filtrar']) && isset($_GET['evento'])) {
    $eventoFiltrado = filter_var($_GET['evento'], FILTER_SANITIZE_SPECIAL_CHARS);
    $filtrar = true;

    // Solo filtrar si el evento es válido
    $eventosValidos = ['canto', 'yardas', 'baile'];
    if (in_array($eventoFiltrado, $eventosValidos)) {
        $query = "SELECT * FROM participantes WHERE evento = " . $pdo->quote($eventoFiltrado);
    }
}

// Ejecutar consulta
$q = $pdo->query($query);
$participantes = $q->fetchAll(PDO::FETCH_ASSOC);

if (!$participantes) {
    $participantes = [];
}

$datos = [
        'participantes' => $participantes
];

$latte = \App\Fabricas\FabricaLatte::obtenerInstancia();

//$latte->render(__DIR__ . '/pdf.latte', $datos);

$html = $latte->renderToString(__DIR__ . '/pdf.latte', $datos);


$pdf = new \Dompdf\Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('Letter');
$options = $pdf->getOptions();
$options->setIsRemoteEnabled(true);
$pdf->setOptions($options);
$pdf->render();

$pdf->stream('agremiados.pdf', ['Attachment' => false]);