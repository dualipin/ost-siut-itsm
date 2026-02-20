<?php

declare(strict_types=1);

use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioPrestamos;
use App\Servicios\ServicioMiembros;
use App\Fabricas\FabricaConexion;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();

header('Content-Type: application/json');

try {
  $solicitudId = (int)($_GET['solicitud_id'] ?? 0);

  if (!$solicitudId) {
    throw new Exception('ID de solicitud requerido');
  }

  $pdo = FabricaConexion::crear();
  $servicioPrestamos = new ServicioPrestamos($pdo);
  $servicioMiembros = new ServicioMiembros($pdo);

  // Verificar que la solicitud pertenece al usuario actual
  $miembro = $servicioMiembros->obtenerPorUsuario($_SESSION['usuario_id']);
  if (!$miembro) {
    throw new Exception('Miembro no encontrado');
  }

  $solicitudes = $servicioPrestamos->obtenerSolicitudesPorMiembro($miembro->id);
  $solicitudValida = false;

  foreach ($solicitudes as $sol) {
    if ($sol['id'] == $solicitudId) {
      $solicitudValida = true;
      break;
    }
  }

  if (!$solicitudValida) {
    throw new Exception('Solicitud no encontrada');
  }

  // Obtener pagos
  $sql = "SELECT * FROM pagos_prestamos WHERE fk_solicitud = ? ORDER BY numero_pago";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$solicitudId]);
  $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'success' => true,
    'pagos' => $pagos
  ]);
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
