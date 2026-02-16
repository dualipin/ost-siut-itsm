<?php

use App\Configuracion\MysqlConexion;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

// ---------- RUTA DE UPLOADS (elige la que quieras) ----------
$uploadDir = __DIR__ . '/../../archivos/subidos/agremiados/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ---------- helpers ----------
function saveFile(array $file, array $allowedExts, int $maxSize, string $dir): string
{
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        throw new RuntimeException('Formato no permitido');
    }
    if ($file['size'] > $maxSize) {
        throw new RuntimeException('Tamaño excedido');
    }
    $name = bin2hex(random_bytes(8)) . ".$ext";
    $dest = $dir . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Error al mover archivo');
    }
    return $name;
}

// ---------- PROCESAR FORMULARIO ----------
$msg = '';
$ok  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = MysqlConexion::conexion();

        // 1. Subida de archivos
        $perfil = saveFile($_FILES['foto_perfil'], ['jpg','jpeg','png'], 2*1024*1024, $uploadDir);
        $afil   = saveFile($_FILES['afiliacion'], ['pdf'], 3*1024*1024, $uploadDir);
        $dom    = saveFile($_FILES['comprobante_domicilio'], ['pdf'], 3*1024*1024, $uploadDir);
        $ine    = saveFile($_FILES['ine'], ['pdf'], 3*1024*1024, $uploadDir);
        $pago   = saveFile($_FILES['comprobante_pago'], ['pdf'], 3*1024*1024, $uploadDir);

        // 2. Transacción
        $pdo->beginTransaction();

        // usuario
        $stmt = $pdo->prepare('INSERT INTO usuarios (correo, contra, rol) VALUES (?,?,?)');
        $stmt->execute([$_POST['correo'], password_hash($_POST['contrasena'], PASSWORD_DEFAULT), 'agremiado']);
        $idUsuario = $pdo->lastInsertId();

        // miembro
        $stmt = $pdo->prepare(
                'INSERT INTO miembros (
                nombre, apellidos, direccion, telefono, categoria, departamento,
                nss, curp, fecha_ingreso, fecha_nacimiento, salario_quincenal, fk_usuario
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
                $_POST['nombre'], $_POST['apellidos'], $_POST['direccion'], $_POST['telefono'],
                $_POST['categoria'], $_POST['departamento'], $_POST['nss'], $_POST['curp'],
                $_POST['fecha_ingreso'], $_POST['fecha_nacimiento'], $_POST['salario_quincenal'], $idUsuario
        ]);
        $idMiembro = $pdo->lastInsertId();

        // documentos
        $stmt = $pdo->prepare(
                'INSERT INTO documentos_agremiados (miembro_id, afiliacion, comprobante_domicilio, ine, comprobante_pago, perfil)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([$idMiembro, $afil, $dom, $ine, $pago, $perfil]);

        $pdo->commit();
        $ok  = true;
        $msg = 'Agremiado registrado con éxito';
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        // opcional: borrar archivos subidos
        foreach ([$perfil ?? '', $afil ?? '', $dom ?? '', $ine ?? '', $pago ?? ''] as $f) @unlink($uploadDir . $f);
        $msg = 'Error: ' . $e->getMessage();
    }
}

// ---------- VISTA ----------
$datos = [
        'ok'  => $ok,
        'msg' => $msg
];
ServicioLatte::renderizar(__DIR__ . '/registrar.latte', $datos);