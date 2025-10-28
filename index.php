<?php
declare(strict_types=1);

use App\Configuracion\MysqlConexion;
use App\Fabricas\FabricaLatte;
use App\Modelos\Visita;
use App\Utilidades\Numero;

require_once __DIR__ . '/src/configuracion.php';

$latte = FabricaLatte::obtenerInstancia();

Visita::agregarVisita();

$visitas = Visita::obtenerVisitasHoy();

// ultimos avisos
$sql = "SELECT 
    id, titulo, resumen, imagen 
    from publicaciones 
    where expiracion IS NULL OR expiracion >= CURDATE() ORDER BY fecha DESC LIMIT 5";

$con = MysqlConexion::conexion();
$stmt = $con->query($sql);
$stmt->execute();
$avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$datos = [
        'visitaPagina' => Numero::formatearNumero($visitas),
        'avisos' => $avisos,
];

$latte->render(__DIR__ . '/index.latte', $datos);
