<?php

use App\Configuracion\MysqlConexion;
use App\Fabricas\FabricaLatte;
use App\Manejadores\SesionProtegida;
use Dompdf\Dompdf;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['administrador', 'lider']);

$pdo = MysqlConexion::conexion();

$latte = FabricaLatte::obtenerInstancia();

$datos = [
        'agremiados' => $pdo->query("SELECT id, nombre, apellidos, curp, telefono, departamento, fecha_ingreso  FROM miembros order by nombre")
                ->fetchAll(PDO::FETCH_ASSOC),
];

$html = $latte->renderToString(__DIR__ . '/pdf.latte', $datos);


$pdf = new Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('Letter');
$options = $pdf->getOptions();
$options->setIsRemoteEnabled(true);
$pdf->setOptions($options);
$pdf->render();

$pdf->stream('agremiados.pdf', ['Attachment' => false]);
