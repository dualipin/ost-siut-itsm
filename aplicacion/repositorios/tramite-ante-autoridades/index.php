<?php

use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;

require_once __DIR__ . '/../../../src/configuracion.php';
require_once __DIR__ . '/../respuesta-documento.php';

SesionProtegida::proteger();

$conn = MysqlConexion::conexion();
$exito = $error = null;

if (isset($_GET['error'])) {
    $error = trim((string)$_GET['error']);
}
if (isset($_GET['mensaje'])) {
    $exito = trim((string)$_GET['mensaje']);
}

$documentos = [];

$porPagina = 6; // cantidad de documentos por página
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$inicio = ($paginaActual - 1) * $porPagina;

$totalStmt = $conn->query("SELECT COUNT(*) FROM documentos_tramites_ante_autoridades");
$totalDocumentos = (int)$totalStmt->fetchColumn();
$totalPaginas = (int)ceil($totalDocumentos / $porPagina);


$sql = "SELECT id, titulo, fecha_subida, privado
        FROM documentos_tramites_ante_autoridades
        ORDER BY fecha_subida DESC
        LIMIT :inicio, :porPagina";

$stmt = $conn->prepare($sql);
$stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
$stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
$stmt->execute();
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

respuestaDocumento(
        __DIR__ . '/index.latte',
        $resultados,
        $documentos,
        $error,
        $exito,
        $paginaActual,
        $totalPaginas
);
