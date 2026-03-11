<?php

require_once __DIR__ . '/../../src/configuracion.php';


$id = $_GET['id'] ?? null;

if ($id == null) {
    header('/index.php');
}


$pdo = \App\Configuracion\MysqlConexion::conexion();
$solicitud = $pdo->prepare("SELECT * FROM solicitudes_prestamos WHERE id = :id");
$solicitud->bindParam(':id', $id);
$solicitud->execute();
$solicitud = $solicitud->fetch(PDO::FETCH_ASSOC);

$miembro = $pdo->prepare("SELECT * FROM miembros WHERE id = :id");
$miembro->bindParam(':id', $solicitud['fk_miembro']);
$miembro->execute();
$miembro = $miembro->fetch(PDO::FETCH_ASSOC);

$formatterES = new NumberFormatter("es", NumberFormatter::SPELLOUT);

$idMiembro = $solicitud['fk_miembro'];
$fecha = $solicitud['fecha_solicitud'];
$monto = $solicitud['monto_aprobado'];
$porcentaje = $solicitud['tasa_interes'];
$montoLetras = $formatterES->format($monto);
$nombreDeudor = $miembro['nombre'] . ' ' . $miembro['apellidos'];
$domicilioDeudor = $miembro['direccion'];
$curpDeudor = $miembro['curp'];
$telefono = $miembro['telefono'];

$cadenaDigital = "Pagare-$idMiembro-$fecha";

$datos = [
        'selloDigital' => hash('md5', $cadenaDigital),
        'monto' => number_format($monto, 2),
        'fechaActual' => date("d \d\\e m \d\\e\l Y"),
        'nombreDeudor' => $nombreDeudor,
        'domicilioDeudor' => $domicilioDeudor ?? 'N/A',
        'curpDeudor' => $curpDeudor ?? 'N/A',
        'telefonoDeudor' => $telefono ?? 'N/A',
        'montoLetras' => $montoLetras,
        'porcentaje' => $porcentaje ?? '12',
];


$latte = \App\Fabricas\FabricaLatte::obtenerInstancia();
$html = $latte->renderToString(__DIR__ . '/pdf-pagare.latte', $datos);


$pdf = new \Dompdf\Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('Letter');
$options = $pdf->getOptions();
$options->setIsRemoteEnabled(true);
$pdf->setOptions($options);
$pdf->render();
$pdf->stream('pagare.pdf', ['Attachment' => true]);