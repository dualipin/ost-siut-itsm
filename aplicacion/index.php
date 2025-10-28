<?php
declare(strict_types=1);

use App\Configuracion\Contexto;
use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../src/configuracion.php';

SesionProtegida::proteger();

$ctx = Contexto::obtenerMiembro();

$usuarioTotales = 0;


if ($ctx->esAdmin() || $ctx->esLider()) {
    $sql = 'SELECT COUNT(*) as total FROM miembros';
    $res = MysqlConexion::conexion()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $usuarioTotales = (int)$res[0]['total'];
}

ServicioLatte::renderizar(__DIR__ . '/index.latte', [
        'usuariosTotales' => $usuarioTotales,
]);