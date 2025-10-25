<?php

declare(strict_types=1);

use App\Fabricas\FabricaConexion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioMiembros;
use App\Servicios\ServicioPrestamos;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger(['administrador', 'finanzas']);

$pdo = FabricaConexion::crear();
$servicioPrestamos = new ServicioPrestamos($pdo);
$servicioMiembros = new ServicioMiembros($pdo);

// Obtener solicitudes pendientes
$solicitudesPendientes = $servicioPrestamos->obtenerSolicitudesPendientes();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $solicitudId = (int)($_POST['solicitud_id'] ?? 0);

    $miembroActual = $servicioMiembros->obtenerPorUsuario($_SESSION['usuario_id']);

    try {
        switch ($accion) {
            case 'aprobar':
                $montoAprobado = (float)($_POST['monto_aprobado'] ?? 0);
                if ($montoAprobado <= 0) {
                    throw new Exception('El monto aprobado debe ser mayor a cero');
                }

                $servicioPrestamos->aprobarSolicitud($solicitudId, $montoAprobado, $miembroActual->id);
                $_SESSION['mensaje_exito'] = 'Solicitud aprobada correctamente';
                break;

            case 'rechazar':
                $motivo = trim($_POST['motivo'] ?? '');
                if (empty($motivo)) {
                    throw new Exception('Debe especificar el motivo del rechazo');
                }

                $servicioPrestamos->rechazarSolicitud($solicitudId, $motivo, $miembroActual->id);
                $_SESSION['mensaje_exito'] = 'Solicitud rechazada';
                break;

            case 'lista_espera':
                $servicioPrestamos->ponerEnListaEspera($solicitudId, $miembroActual->id);
                $_SESSION['mensaje_exito'] = 'Solicitud puesta en lista de espera';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['mensaje_error'] = $e->getMessage();
    }

    header('Location: administrar.php');
    exit;
}

$datos = [
        'solicitudes' => $solicitudesPendientes,
        'titulo' => 'Administrar Préstamos',
        'error' => $_SESSION['mensaje_error'] ?? null,
];

\App\Servicios\ServicioLatte::renderizar(__DIR__ . '/../plantillas/prestamos-administrar.latte', $datos);
