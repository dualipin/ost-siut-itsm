<?php

use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();

// Solo finanzas/administrador pueden ver y gestionar
if (!SesionProtegida::tieneRol(['administrador', 'finanzas'])) {
	header('Location: /aplicacion/index.php');
	exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$solicitudes = $pdo->query("SELECT s.*, m.nombre, m.apellidos FROM solicitudes_prestamos s JOIN miembros m ON s.fk_miembro = m.id WHERE s.estado IN ('pendiente', 'lista_espera') ORDER BY s.fecha_solicitud ASC")->fetchAll(PDO::FETCH_ASSOC);

$mensaje = $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['id'])) {
	$id = intval($_POST['id']);
	$accion = $_POST['accion'];
	if ($accion === 'aprobar') {
		$monto_aprobado = floatval($_POST['monto_aprobado'] ?? 0);
		$stmt = $pdo->prepare("UPDATE solicitudes_prestamos SET estado = 'pagare_pendiente', monto_aprobado = ? WHERE id = ?");
		$stmt->execute([$monto_aprobado, $id]);
		$mensaje = 'Solicitud aprobada.';
	} elseif ($accion === 'rechazar') {
		$stmt = $pdo->prepare("UPDATE solicitudes_prestamos SET estado = 'rechazado' WHERE id = ?");
		$stmt->execute([$id]);
		$mensaje = 'Solicitud rechazada.';
	} elseif ($accion === 'espera') {
		$stmt = $pdo->prepare("UPDATE solicitudes_prestamos SET estado = 'lista_espera' WHERE id = ?");
		$stmt->execute([$id]);
		$mensaje = 'Solicitud puesta en lista de espera.';
	}
	header('Location: solicitudes-prestamos.php?mensaje=' . urlencode($mensaje));
	exit;
}

$datos = [
	'solicitudes' => $solicitudes,
	'mensaje' => $_GET['mensaje'] ?? null,
	'error' => $error,
];
ServicioLatte::renderizar(__DIR__ . '/solicitudes-prestamos.latte', $datos);