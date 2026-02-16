<?php

declare(strict_types=1);

use App\Manejadores\Sesion;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../src/configuracion.php';

\App\Manejadores\SesionProtegida::proteger();
$pdo = \App\Configuracion\MysqlConexion::conexion();

$miembro = Sesion::sesionAbierta();
$rol = $miembro->getRol() ?? 'agremiado';
$idMiembro = $miembro->getId() ?? null;

/* KPIs globales */
$visitasHoy = $pdo->query("SELECT COUNT(*) FROM visitas_pagina WHERE DATE(fecha)=CURDATE()")->fetchColumn();
$prestamosActivos = $pdo->query(
        "SELECT COUNT(*) FROM solicitudes_prestamos WHERE estado IN ('activo','pagare_pendiente')"
)->fetchColumn();
$publicaciones = $pdo->query(
        "SELECT COUNT(*) FROM publicaciones WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetchColumn();

/* Datos por rol */
$misPrestamos = [];
$docsFaltantes = 0;
$propuestasPend = 0;
$miembrosInactiv = 0;

if ($idMiembro) {
    $misPrestamos = $pdo->prepare(
            "SELECT id,estado,monto_solicitado,fecha_solicitud FROM solicitudes_prestamos WHERE fk_miembro=? ORDER BY fecha_solicitud DESC LIMIT 5"
    );
    $misPrestamos->execute([$idMiembro]);
    $misPrestamos = $misPrestamos->fetchAll(PDO::FETCH_ASSOC);

    $docs = $pdo->prepare(
            "SELECT afiliacion,comprobante_domicilio,ine,comprobante_pago FROM documentos_agremiados WHERE miembro_id=?"
    );
    $docs->execute([$idMiembro]);
    $row = $docs->fetch(PDO::FETCH_ASSOC) ?: [];
    $docsFaltantes = count(
            array_filter(['afiliacion', 'comprobante_domicilio', 'ine', 'comprobante_pago'], fn($k) => empty($row[$k]))
    );
}

if ($rol === 'lider') {
    $propuestasPend = $pdo->query("SELECT COUNT(*) FROM propuestas")->fetchColumn();
}

if ($rol === 'administrador') {
    $miembrosInactiv = $pdo->query("SELECT COUNT(*) FROM miembros WHERE activo=0")->fetchColumn();
}

/* Noticias */
$noticias = $pdo->query(
        "SELECT id,titulo,resumen,fecha FROM publicaciones WHERE tipo='noticia' ORDER BY fecha DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

ServicioLatte::renderizar(__DIR__ . '/index.latte', [
        'usuario' => $miembro->getNombreCompleto(),
        'rol' => $rol,
        'visitasHoy' => $visitasHoy,
        'prestamosActivos' => $prestamosActivos,
        'publicaciones' => $publicaciones,
        'misPrestamos' => $misPrestamos,
        'docsFaltantes' => $docsFaltantes,
        'propuestasPend' => $propuestasPend,
        'miembrosInactiv' => $miembrosInactiv,
        'noticias' => $noticias,
]);