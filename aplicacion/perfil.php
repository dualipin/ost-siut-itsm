<?php

declare(strict_types=1);

use App\Configuracion\MysqlConexion;
use App\Manejadores\Sesion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;
use App\Entidades\EntidadMiembro;

require_once __DIR__ . '/../src/configuracion.php';
$baseUrl = '/archivos/subidos/agremiados/';

SesionProtegida::proteger();

$uploadDir = __DIR__.'/../archivos/subidos/agremiados/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
$allowedImg = ['jpg', 'jpeg', 'png'];
$allowedPdf = ['pdf'];
$maxImgSize = 2 * 1024 * 1024;
$maxPdfSize = 3 * 1024 * 1024;

// ---------- ACTUALIZAR ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $pdo = MysqlConexion::conexion();
        $idMiembro = (int)($_POST['id_miembro'] ?? 0);
        if (!$idMiembro) throw new RuntimeException('ID faltante');

        // 1. Subida de archivos (solo si se envían)
        $docsToUpdate = [];
        foreach ([
                         'fotoPerfil' => ['ext' => $allowedImg, 'size' => $maxImgSize, 'col' => 'perfil'],
                         'afiliacion' => ['ext' => $allowedPdf, 'size' => $maxPdfSize, 'col' => 'afiliacion'],
                         'comprobante_domicilio' => ['ext' => $allowedPdf, 'size' => $maxPdfSize, 'col' => 'comprobante_domicilio'],
                         'ine' => ['ext' => $allowedPdf, 'size' => $maxPdfSize, 'col' => 'ine'],
                         'comprobante_pago' => ['ext' => $allowedPdf, 'size' => $maxPdfSize, 'col' => 'comprobante_pago'],
                 ] as $key => $rule) {
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $rule['ext'])) throw new RuntimeException("Formato no permitido: $key");
                if ($_FILES[$key]['size'] > $rule['size']) throw new RuntimeException("Tamaño excedido: $key");
                $name = bin2hex(random_bytes(8)) . ".$ext";
                $dest = $uploadDir . $name;
                if (!move_uploaded_file($_FILES[$key]['tmp_name'], $dest)) throw new RuntimeException("Error al mover: $key");
                $docsToUpdate[$rule['col']] = $name;
            }
        }

        // 2. Transacción
        $pdo->beginTransaction();

        // 2a. Usuario (correo + contraseña opcional)
        $stmt = $pdo->prepare('SELECT fk_usuario FROM miembros WHERE id = ?');
        $stmt->execute([$idMiembro]);
        $userId = (int)$stmt->fetchColumn();

        $sqlUser = 'UPDATE usuarios SET correo = ?';
        $paramsUser = [$_POST['correo']];
        if (!empty($_POST['contra'])) {
            $sqlUser .= ', contra = ?';
            $paramsUser[] = password_hash($_POST['contra'], PASSWORD_DEFAULT);
        }
        $sqlUser .= ' WHERE id = ?';
        $paramsUser[] = $userId;
        $stmt = $pdo->prepare($sqlUser);
        $stmt->execute($paramsUser);

        // 2b. Miembro
        $stmt = $pdo->prepare(
                'UPDATE miembros SET direccion = ?, telefono = ? WHERE id = ?'
        );
        $stmt->execute([$_POST['direccion'], $_POST['telefono'], $idMiembro]);

        // 2c. Documentos (solo si se subió algo)
// 2c. Documentos (inserta o actualiza)
        if ($docsToUpdate) {
            // Obtenemos los nombres de archivo que YA tiene (si existe fila)
            $stmt = $pdo->prepare('SELECT * FROM documentos_agremiados WHERE miembro_id = ?');
            $stmt->execute([$idMiembro]);
            $rowExistente = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Merge: conserva los anteriores + sobrescribe los nuevos
            $final = array_merge(
                    [
                            'afiliacion'             => $rowExistente['afiliacion'] ?? '',
                            'comprobante_domicilio'  => $rowExistente['comprobante_domicilio'] ?? '',
                            'ine'                    => $rowExistente['ine'] ?? '',
                            'comprobante_pago'       => $rowExistente['comprobante_pago'] ?? '',
                            'perfil'                 => $rowExistente['perfil'] ?? '',
                    ],
                    $docsToUpdate
            );

            // ¿Existe fila?
            $existe = (bool) $rowExistente;

            if ($existe) {
                // UPDATE
                $sets = [];
                foreach ($final as $col => $val) $sets[] = "$col = ?";
                $sql = 'UPDATE documentos_agremiados SET ' . implode(', ', $sets) . ' WHERE miembro_id = ?';
                $values = array_values($final);
                $values[] = $idMiembro;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
            } else {
                // INSERT con TODAS las columnas
                $cols = implode(', ', array_keys($final));
                $place = rtrim(str_repeat('?, ', count($final)), ', ');
                $sql = "INSERT INTO documentos_agremiados (miembro_id, $cols) VALUES (?, $place)";
                $values = array_values($final);
                array_unshift($values, $idMiembro);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
            }
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'msg' => 'Perfil actualizado']);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit; // ← importante, no renderizar plantilla
}

$con = MysqlConexion::conexion();

// 1. Obtener ID del usuario logueado (ajusta a tu sesión)
$userId = Sesion::idSesionAbierta();

// ← tu clave de sesión
$stmt = $con->prepare('SELECT * FROM miembros WHERE id = ?');
$stmt->execute([$userId]);
$raw = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$raw) {
    header('Location: /aplicacion/');
    exit;
}

$stmt = $con->prepare('SELECT correo, rol FROM usuarios WHERE id = ?');
$stmt->execute([$raw['fk_usuario']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
$raw['correo'] = $usuario['correo'];
$raw['rol'] = $usuario['rol'];


//
//// 3. Documentos (obligatorios)
$stmt = $con->prepare('SELECT * FROM documentos_agremiados WHERE miembro_id = ?');
$stmt->execute([$userId]);
$docs = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;


//
//// 4. Ruta pública

$datos = [
        'perfil' => $raw,
        'docs' => $docs,
        'baseUrl' => $baseUrl
];
//
ServicioLatte::renderizar(__DIR__ . '/plantillas/perfil.latte', $datos);
