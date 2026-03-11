<?php

declare(strict_types=1);

use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioMiembros;
use App\Fabricas\FabricaConexion;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger(['administrador']);

$pdo = FabricaConexion::crear();
$servicioMiembros = new ServicioMiembros($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $miembroId = (int)($_POST['miembro_id'] ?? 0);
    $salario = (float)($_POST['salario_quincenal'] ?? 0);

    if (!$miembroId || $salario <= 0) {
      throw new Exception('Datos inválidos');
    }

    $servicioMiembros->actualizarSalarioQuincenal($miembroId, $salario);
    $_SESSION['mensaje_exito'] = 'Salario actualizado correctamente';
  } catch (Exception $e) {
    $_SESSION['mensaje_error'] = $e->getMessage();
  }

  header('Location: actualizar-salario.php');
  exit;
}

$miembros = $servicioMiembros->obtenerTodos();

$datos = [
  'miembros' => $miembros,
  'titulo' => 'Actualizar Salarios Quincenales'
];

\App\Servicios\ServicioLatte::renderizar(__DIR__ . '/../plantillas/prestamos-salarios.latte', $datos);
