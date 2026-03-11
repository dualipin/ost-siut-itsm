<?php

declare(strict_types=1);

use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioPrestamos;
use App\Servicios\ServicioMiembros;
use App\Entidades\SolicitudPrestamo;
use App\Fabricas\FabricaConexion;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: solicitar.php');
  exit;
}

try {
  $pdo = FabricaConexion::crear();
  $servicioPrestamos = new ServicioPrestamos($pdo);
  $servicioMiembros = new ServicioMiembros($pdo);

  // Obtener miembro actual
  $miembro = $servicioMiembros->obtenerPorUsuario($_SESSION['usuario_id']);
  if (!$miembro) {
    throw new Exception('No se encontró el perfil del miembro');
  }

  // Validar datos del formulario
  $montoSolicitado = (float)($_POST['monto_solicitado'] ?? 0);
  $plazoMeses = (int)($_POST['plazo_meses'] ?? 0);
  $tipoDescuento = $_POST['tipo_descuento'] ?? 'quincenal';
  $justificacion = trim($_POST['justificacion'] ?? '');

  if ($montoSolicitado <= 0) {
    throw new Exception('El monto solicitado debe ser mayor a cero');
  }

  if ($plazoMeses <= 0) {
    throw new Exception('El plazo debe ser mayor a cero');
  }

  if (empty($justificacion)) {
    throw new Exception('La justificación es obligatoria');
  }

  // Validar archivo de recibo de nómina
  if (!isset($_FILES['recibo_nomina']) || $_FILES['recibo_nomina']['error'] !== UPLOAD_ERR_OK) {
    throw new Exception('Debe adjuntar el recibo de nómina');
  }

  $archivo = $_FILES['recibo_nomina'];
  $extensionesPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
  $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

  if (!in_array($extension, $extensionesPermitidas)) {
    throw new Exception('Solo se permiten archivos PDF, JPG, JPEG o PNG');
  }

  // Validar capacidad de pago
  $validacion = $servicioPrestamos->validarCapacidadPago($miembro->id, $montoSolicitado);
  if (!$validacion['valido']) {
    throw new Exception($validacion['mensaje']);
  }

  // Crear directorio si no existe
  $directorioSubida = __DIR__ . '/../../temp/recibos-nomina/';
  if (!is_dir($directorioSubida)) {
    mkdir($directorioSubida, 0755, true);
  }

  // Generar nombre único para el archivo
  $nombreArchivo = 'recibo_' . $miembro->id . '_' . time() . '.' . $extension;
  $rutaCompleta = $directorioSubida . $nombreArchivo;

  if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
    throw new Exception('Error al subir el archivo');
  }

  // Crear solicitud
  $solicitud = new SolicitudPrestamo(
    montoSolicitado: $montoSolicitado,
    plazoMeses: $plazoMeses,
    tipoDescuento: $tipoDescuento,
    justificacion: $justificacion,
    reciboNomina: $nombreArchivo,
    fkMiembro: $miembro->id
  );

  $solicitudId = $servicioPrestamos->crearSolicitud($solicitud);

  $_SESSION['mensaje_exito'] = 'Solicitud de préstamo enviada correctamente. Número de solicitud: ' . $solicitudId;
  header('Location: index.php');
} catch (Exception $e) {
  $_SESSION['mensaje_error'] = $e->getMessage();
  header('Location: solicitar.php');
}
