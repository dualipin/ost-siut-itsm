<?php

use App\Configuracion\MysqlConexion;

require_once __DIR__ . '/../../src/configuracion.php';

header('Content-Type: application/json');

// ---------- CONFIG ----------
$uploadDir = __DIR__ . '/../../archivos/subidos/agremiados/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$allowedImg = ['jpg', 'jpeg', 'png'];
$allowedPdf = ['pdf'];
$maxImgSize = 2 * 1024 * 1024;
$maxPdfSize = 3 * 1024 * 1024;

// ---------- HELPERS ----------
function jsonResponse(bool $ok, string $msg = '', array $extra = []): never
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

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

// ---------- VALIDACIÓN BÁSICA ----------
$idAgremiado = (int)($_POST['id_agremiado'] ?? 0);
if (!$idAgremiado) {
    jsonResponse(false, 'ID de agremiado faltante');
}

$pdo = MysqlConexion::conexion();

// Verificar que existe
$stmt = $pdo->prepare('SELECT id, fk_usuario FROM miembros WHERE id = ?');
$stmt->execute([$idAgremiado]);
$miembro = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$miembro) {
    jsonResponse(false, 'Agremiado no encontrado');
}

$idUsuario = (int)$miembro['fk_usuario'];

// ---------- SUBIDA DE ARCHIVOS (solo si se envían) ----------
$docsToUpdate = [];
try {
    foreach (
            [
                    'foto_perfil' => ['ext' => $allowedImg, 'size' => $maxImgSize],
                    'afiliacion' => ['ext' => $allowedPdf, 'size' => $maxPdfSize],
                    'comprobante_domicilio' => ['ext' => $allowedPdf, 'size' => $maxPdfSize],
                    'ine' => ['ext' => $allowedPdf, 'size' => $maxPdfSize],
                    'comprobante_pago' => ['ext' => $allowedPdf, 'size' => $maxPdfSize],
            ] as $key => $rule
    ) {
        if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
            $docsToUpdate[$key] = saveFile($_FILES[$key], $rule['ext'], $rule['size'], $uploadDir);
        }
    }
} catch (Throwable $e) {
    jsonResponse(false, $e->getMessage());
}

// ---------- ACTUALIZACIÓN ----------
try {
    $pdo->beginTransaction();

    // 1. Usuario (solo correo y contraseña si se envía)
    $correo = $_POST['correo'];
    $sqlUsuario = 'UPDATE usuarios SET correo = ?';
    $paramsUsuario = [$correo];

    if (!empty($_POST['contrasena'])) {
        $sqlUsuario .= ', contra = ?';
        $paramsUsuario[] = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    }
    $sqlUsuario .= ' WHERE id = ?';
    $paramsUsuario[] = $idUsuario;

    $stmt = $pdo->prepare($sqlUsuario);
    $stmt->execute($paramsUsuario);

    // 2. Miembro
    $stmt = $pdo->prepare(
            'UPDATE miembros SET
            nombre = ?, apellidos = ?, direccion = ?, telefono = ?,
            categoria = ?, departamento = ?, nss = ?, curp = ?,
            fecha_ingreso = ?, fecha_nacimiento = ?, salario_quincenal = ?
         WHERE id = ?'
    );
    $stmt->execute([
            $_POST['nombre'],
            $_POST['apellidos'],
            $_POST['direccion'],
            $_POST['telefono'],
            $_POST['categoria'],
            $_POST['departamento'],
            $_POST['nss'],
            $_POST['curp'],
            $_POST['fecha_ingreso'],
            $_POST['fecha_nacimiento'],
            $_POST['salario_quincenal'],
            $idAgremiado
    ]);

    // 3. Documentos (solo si se subió algo)
// 3. Documentos (solo si se subió algo)
    if ($docsToUpdate) {
        $fields = [];
        foreach ($docsToUpdate as $col => $filename) {
            // ← mapeo clave form → columna DB
            $dbCol = match($col) {
                'foto_perfil' => 'perfil',
                default       => $col
            };
            $fields[] = "$dbCol = ?";
        }
        $sql = 'UPDATE documentos_agremiados SET ' . implode(', ', $fields) . ' WHERE miembro_id = ?';
        $stmt = $pdo->prepare($sql);
        $values = array_values($docsToUpdate);
        $values[] = $idAgremiado;
        $stmt->execute($values);
    }

    $pdo->commit();
    jsonResponse(true, 'Datos actualizados correctamente');
} catch (Throwable $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Error al actualizar: ' . $e->getMessage());
}