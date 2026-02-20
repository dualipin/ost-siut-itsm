<?php

declare(strict_types=1);

use App\Fabricas\FabricaConexion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioMiembros;
use App\Servicios\ServicioPrestamos;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger(['administrador', 'finanzas']);

$pdo = \App\Configuracion\MysqlConexion::conexion();
$prestamos = $pdo->query("select p.id,
       p.monto_solicitado,
       p.plazo_meses,
       p.fecha_solicitud,
        p.recibo_nomina,
        p.plazo_meses,
       p.estado,
       m.nombre    as nombre_miembro,
       m.apellidos as apellidos_miembro
from solicitudes_prestamos p
         join miembros m on p.fk_miembro = m.id")->fetchAll(PDO::FETCH_ASSOC);


if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($prestamos);
    exit;
}


$datos = [
        'solicitudes' => $prestamos,
        'titulo' => 'Administrar Préstamos',
        'error' => $_SESSION['mensaje_error'] ?? null,
];

\App\Servicios\ServicioLatte::renderizar(__DIR__ . '/administrar.latte', $datos);
