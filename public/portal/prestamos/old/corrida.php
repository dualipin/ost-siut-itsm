<?php

require_once __DIR__ . '/../../src/configuracion.php';
require_once __DIR__ . '/generador-corrida.php';


$id = $_GET['id'] ?? null;

$pdo = \App\Configuracion\MysqlConexion::conexion();
$validar = $pdo->prepare("SELECT * FROM solicitudes_prestamos WHERE id = :id");
$validar->bindParam(':id', $id);
$validar->execute();
$prestamo = $validar->fetch(PDO::FETCH_ASSOC);


if ($prestamo === null) {
    header('Location: index.php');
    exit;
}

$miembro = $pdo->prepare("SELECT * FROM miembros WHERE id = :miembro_id");
$miembro->bindParam(':miembro_id', $prestamo['fk_miembro']);
$miembro->execute();
$prestamo['miembro'] = $miembro->fetch(PDO::FETCH_ASSOC);


$tabla = calcularInteres($prestamo['monto_aprobado'], $prestamo['tasa_interes'], $prestamo['plazo_meses']);
$totales = calcularPagoTotal($tabla);


$fecha = new DateTime();
$fecha->modify('first day of this month');


foreach ($tabla as $key => $fila) {
    // 2. Incrementamos UN mes al inicio de cada ciclo
    // (para que el primer pago sea el mes siguiente a la aprobación)
    $fecha->modify('+1 month');


    $tabla[$key]['fecha'] = $fecha->format('d/m/y');
}

$datosSello = "Prestamo-{$prestamo['id']}-miembro-{$prestamo['fk_miembro']}";

$datos = [
        'tabla_amortizacion' => $tabla,
        'totales' => $totales,
        'prestamo' => $prestamo,
        'sello' => hash('md5', $datosSello),
];

$latte = \App\Fabricas\FabricaLatte::obtenerInstancia();

$pdf = new \Dompdf\Dompdf();
$html = $latte->renderToString(__DIR__ . '/corrida.latte', $datos);
$pdf->loadHtml($html);
$pdf->setPaper('Letter');
$options = $pdf->getOptions();
$options->setIsRemoteEnabled(true);
$pdf->setOptions($options);
$pdf->render();
$pdf->stream('pagare.pdf', ['Attachment' => true]);