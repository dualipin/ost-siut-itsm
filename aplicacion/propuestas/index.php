<?php

use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['administrador', 'lider']);


$sql = " SELECT m.id AS miembro_id, m.nombre, m.apellidos, m.departamento, m.telefono, m.fecha_ingreso, p.id AS propuesta_id, pc.contenido, pc.tipo FROM miembros m LEFT JOIN propuestas p ON m.id = p.miembro_id LEFT JOIN propuestas_contenido pc ON p.id = pc.propuesta_id ORDER BY m.id, p.id ; ";


$pdo = MysqlConexion::conexion();

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


// 🔹 Reorganizamos los datos por miembro
$miembros = [];
foreach ($rows as $row) {
    $id = $row['miembro_id'];
    if (!isset($miembros[$id])) {
        $miembros[$id] = [
                'id' => $row['miembro_id'],
                'nombre' => $row['nombre'],
                'apellidos' => $row['apellidos'],
                'departamento' => $row['departamento'],
                'telefono' => $row['telefono'],
                'fecha_ingreso' => $row['fecha_ingreso'],
                'propuestas' => [],
        ];
    }

    if ($row['propuesta_id']) {
        $miembros[$id]['propuestas'][] = [
                'contenido' => $row['contenido'],
                'tipo' => $row['tipo'],
        ];
    }
}

$datos = [
        'miembros' => $miembros,
];

ServicioLatte::renderizar(__DIR__ . '/index.latte', $datos);