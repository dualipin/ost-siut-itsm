<?php

declare(strict_types=1);

use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioPrestamos;
use App\Servicios\ServicioMiembros;
use App\Fabricas\FabricaConexion;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();

$pdo = FabricaConexion::crear();
$servicioPrestamos = new ServicioPrestamos($pdo);
$servicioMiembros = new ServicioMiembros($pdo);

$solicitudId = (int)($_GET['id'] ?? 0);

if (!$solicitudId) {
  header('Location: index.php');
  exit;
}

// Verificar que la solicitud pertenece al usuario actual
$miembro = $servicioMiembros->obtenerPorUsuario($_SESSION['usuario_id']);
$solicitudes = $servicioPrestamos->obtenerSolicitudesPorMiembro($miembro->id);
$solicitudValida = false;

foreach ($solicitudes as $sol) {
  if ($sol['id'] == $solicitudId && $sol['estado'] === 'pagare_pendiente') {
    $solicitudValida = true;
    break;
  }
}

if (!$solicitudValida) {
  $_SESSION['mensaje_error'] = 'Solicitud no encontrada o no disponible para generar pagaré';
  header('Location: index.php');
  exit;
}

// Procesar subida de pagaré firmado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_pagare'])) {
  try {
    if (!isset($_FILES['pagare_firmado']) || $_FILES['pagare_firmado']['error'] !== UPLOAD_ERR_OK) {
      throw new Exception('Debe adjuntar el pagaré firmado');
    }

    $archivo = $_FILES['pagare_firmado'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if ($extension !== 'pdf') {
      throw new Exception('El pagaré debe estar en formato PDF');
    }

    // Crear directorio si no existe
    $directorioSubida = __DIR__ . '/../../temp/pagares-firmados/';
    if (!is_dir($directorioSubida)) {
      mkdir($directorioSubida, 0755, true);
    }

    // Generar nombre único para el archivo
    $nombreArchivo = 'pagare_firmado_' . $solicitudId . '_' . time() . '.pdf';
    $rutaCompleta = $directorioSubida . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
      throw new Exception('Error al subir el archivo');
    }

    $servicioPrestamos->subirPagareFirmado($solicitudId, $nombreArchivo);
    $_SESSION['mensaje_exito'] = 'Pagaré firmado subido correctamente. Su préstamo está ahora activo.';
    header('Location: index.php');
    exit;
  } catch (Exception $e) {
    $_SESSION['mensaje_error'] = $e->getMessage();
  }
}

// Generar datos del pagaré
$datosPagare = $servicioPrestamos->generarPagare($solicitudId);

if (empty($datosPagare)) {
  $_SESSION['mensaje_error'] = 'No se pudo generar el pagaré';
  header('Location: index.php');
  exit;
}

// Si se solicita descarga del pagaré
if (isset($_GET['descargar'])) {
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="pagare_' . $datosPagare['numero_pagare'] . '.pdf"');

  // Aquí generarías el PDF del pagaré
  // Por simplicidad, mostraré el contenido como texto
  echo "PAGARÉ\n\n";
  echo "Número: " . $datosPagare['numero_pagare'] . "\n";
  echo "Fecha: " . $datosPagare['fecha_generacion'] . "\n\n";
  echo "Yo, " . $datosPagare['solicitud']['nombre'] . " " . $datosPagare['solicitud']['apellidos'] . "\n";
  echo "NSS: " . $datosPagare['solicitud']['nss'] . "\n";
  echo "CURP: " . $datosPagare['solicitud']['curp'] . "\n\n";
  echo "Me comprometo a pagar la cantidad de $" . number_format($datosPagare['solicitud']['monto_aprobado'], 2) . "\n";
  echo "En " . count($datosPagare['corrida_financiera']) . " pagos según la siguiente tabla:\n\n";

  foreach ($datosPagare['corrida_financiera'] as $pago) {
    echo "Pago " . $pago['numero'] . ": $" . number_format($pago['monto'], 2) . " - " . $pago['fecha'] . "\n";
  }

  echo "\n\nFirma: _________________________\n";
  exit;
}

$datos = [
  'pagare' => $datosPagare,
  'titulo' => 'Pagaré - ' . $datosPagare['numero_pagare']
];

\App\Servicios\ServicioLatte::renderizar(__DIR__ . '/../plantillas/prestamos-pagare.latte', $datos);
