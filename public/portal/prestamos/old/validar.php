<?php

require_once __DIR__ . '/../../src/configuracion.php';
require_once __DIR__ . '/generador-corrida.php';


$id = $_POST['id'] ?? null;

$pdo = \App\Configuracion\MysqlConexion::conexion();
$validar = $pdo->prepare("SELECT * FROM solicitudes_prestamos WHERE id = :id");
$validar->bindParam(':id', $id);
$validar->execute();
$prestamo = $validar->fetch(PDO::FETCH_ASSOC);

if ($prestamo === null) {
    header('Location: index.php');
    exit;
}

$pdo->prepare(
        "UPDATE solicitudes_prestamos SET estado = 'activo', motivo_rechazo = '', fk_aprobador = :aprobador, fecha_respuesta = NOW() WHERE id = :id"
)
        ->execute([
                ':aprobador' => \App\Manejadores\Sesion::sesionAbierta()->getId(),
                ':id' => $id,
        ]);

// creacion de la corrida
$tabla = calcularInteres($prestamo['monto_aprobado'], $prestamo['tasa_interes'], $prestamo['plazo_meses']);
$totales = calcularPagoTotal($tabla);

$fecha = new DateTime(); // Fecha actual (2026-01-04 según el sistema)

$fechas_mensuales = [];

$pagosExistentes = $pdo->prepare("DELETE FROM pagos_prestamos WHERE fk_solicitud = :fk_solicitud");
$pagosExistentes->execute([':fk_solicitud' => $prestamo['id']]);

$fecha = new DateTime();
$fecha->modify('first day of this month');

foreach ($tabla as $key => $fila) {
    // 2. Incrementamos UN mes al inicio de cada ciclo
    // (para que el primer pago sea el mes siguiente a la aprobación)
    $fecha->modify('+1 month');

    $pdo->prepare(
            "INSERT INTO pagos_prestamos (numero_pago, monto_pago, fecha_programada, fecha_pago, estado, fk_solicitud) 
             VALUES (:numero_pago, :monto_pago, :fecha_programada, NULL, 'pendiente', :fk_solicitud)"
    )->execute([
            ':numero_pago' => $fila['mes'],
            ':monto_pago' => $fila['pago_mes'],
            ':fecha_programada' => $fecha->format('Y-m-d'), // Formato correcto para SQL
            ':fk_solicitud' => $prestamo['id'],
    ]);

    $tabla[$key]['fecha'] = $fecha->format('d/m/y');
}

$datosSello = "Prestamo-{$prestamo['id']}-miembro-{$prestamo['fk_miembro']}";

$datos = [
        'tabla_amortizacion' => $tabla,
        'totales' => $totales,
        'prestamo' => $prestamo,
        'sello' => hash('md5', $datosSello),
];

$r = http_build_query([
        'id' => $id,
        'message' => 'Préstamo aprobado y activado.',
]);

header("Location: ver.php?$r");
exit;

//$latte = \App\Fabricas\FabricaLatte::obtenerInstancia();
//
//$pdf = new \Dompdf\Dompdf();
//$html = $latte->renderToString(__DIR__ . '/corrida.latte', $datos);
//$pdf->loadHtml($html);
//$pdf->setPaper('Letter');
//$options = $pdf->getOptions();
//$options->setIsRemoteEnabled(true);
//$pdf->setOptions($options);
//$pdf->render();
//$pdf->stream('pagare.pdf', ['Attachment' => true]);