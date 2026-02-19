<?php

declare(strict_types=1);

use App\Manejadores\Sesion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();

$mensaje = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_miembro = $_POST['miembro_id'] ?? Sesion::sesionAbierta()->getId();
    $monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0;
    $plazo = isset($_POST['plazo']) ? intval($_POST['plazo']) : 0;
    $tipo_descuento = $_POST['tipo_descuento'] ?? '';
    $justificacion = $_POST['justificacion'] ?? '';

    // Validar tipo de descuento
    $tipos_validos = ['quincenal', 'aguinaldo', 'prima_vacacional'];
    if (!in_array($tipo_descuento, $tipos_validos, true)) {
        $error = 'Debes seleccionar un tipo de descuento válido.';
    }

    // Validar recibo de nómina
    if (!isset($_FILES['recibo-salario']) || $_FILES['recibo-salario']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Debes adjuntar el recibo de nómina correspondiente.';
    } else {
        $archivo = $_FILES['recibo-salario'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $error = 'El recibo de nómina debe estar en formato PDF.';
        } elseif ($archivo['size'] > 5 * 1024 * 1024) {
            $error = 'El archivo excede el tamaño máximo permitido (5MB).';
        }
    }

    if ($error === null) {
        // Guardar archivo
        $directorio = __DIR__ . '/../../archivos/subidos/recibos_nomina/';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0777, true);
        }
        $nombreArchivo = uniqid('recibo_', true) . '.pdf';
        $rutaDestino = $directorio . $nombreArchivo;
        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            $error = 'Error al guardar el recibo de nómina.';
        } else {
            // Guardar solicitud en la base de datos
            $pdo = \App\Configuracion\MysqlConexion::conexion();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = "INSERT INTO solicitudes_prestamos (monto_solicitado, plazo_meses, tipo_descuento, recibo_nomina, estado, fk_miembro, justificacion) VALUES (?, ?, ?, ?, 'pendiente', ?, ?)";
            $stmt = $pdo->prepare($sql);
            $id_miembro = $_POST['miembro_id'] ?? Sesion::sesionAbierta()->getId();
            if ($id_miembro && $stmt->execute(
                            [$monto, (int)($plazo / 2), $tipo_descuento, $nombreArchivo, $id_miembro, $justificacion]
                    )) {
                $mensaje = 'Solicitud registrada correctamente.';
                header('Location: solicitar.php?msg=' . urlencode($mensaje));
            } else {
                header('Location: solicitar.php?error=' . urlencode('Error al registrar la solicitud.'));
            }
            exit();
        }
    }
}

$mensaje = $_GET['msg'] ?? $mensaje;
$error = $_GET['error'] ?? $error;

$miembros = [];

$usuario = Sesion::sesionAbierta();

if ($usuario->esAdmin() || $usuario->esLider()) {
    $pdo = \App\Configuracion\MysqlConexion::conexion();
    $miembros = $pdo->query("SELECT id, nombre, apellidos FROM miembros WHERE activo = true ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
}

$pdo = \App\Configuracion\MysqlConexion::conexion();
$categoriasTipoIngresoRows = $pdo->query("SELECT tipo_ingreso_id, nombre, descripcion, es_periodico, frecuencia_dias, mes_pago_tentativo, dia_pago_tentativo, activo FROM cat_tipos_ingreso WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$categoriasTipoIngreso = array_map(function($r) {
    return [
        'id' => (int) $r['tipo_ingreso_id'],
        'nombre' => $r['nombre'],
        'descripcion' => $r['descripcion'] ?? null,
        'esPeriodico' => (bool) $r['es_periodico'],
        'frecuenciaDias' => $r['frecuencia_dias'] !== null ? (int) $r['frecuencia_dias'] : null,
        'mesPagoTentativo' => $r['mes_pago_tentativo'] ?? null,
        'diaPagoTentativo' => $r['dia_pago_tentativo'] ?? null,
        'activo' => (bool) $r['activo'],
    ];
}, $categoriasTipoIngresoRows);

$datos = [
        'mensaje' => $mensaje,
        'error' => $error,
        'miembros' => $miembros,
        'categoriasTipoIngreso' => $categoriasTipoIngreso,
];
ServicioLatte::renderizar(__DIR__ . '/solicitar.latte', $datos);