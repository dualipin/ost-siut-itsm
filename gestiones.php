<?php
declare(strict_types=1);

use App\Configuracion\MysqlConexion;
use App\Fabricas\FabricaLatte;

require_once __DIR__ . '/src/configuracion.php';

$pdo = MysqlConexion::conexion();
$query = $pdo->query("SELECT * FROM publicaciones WHERE tipo = 'gestiones' ORDER BY fecha DESC");
$avisos = $query->fetchAll(PDO::FETCH_ASSOC);

$datos = ['gestiones' => $avisos];

FabricaLatte::obtenerInstancia()
        ->render(__DIR__ . '/gestiones.latte', $datos);