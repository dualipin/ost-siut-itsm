<?php

require_once __DIR__ . '/../src/configuracion.php';

use App\Configuracion\MysqlConexion;

header('Content-Type: application/json'); // Importante para que JS entienda la respuesta


try {
    $pdo = MysqlConexion::conexion();

    // 1. Validar que sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // 2. Recibir datos básicos
    $nombre = $_POST['nombre'] ?? '';
    $area = $_POST['area'] ?? '';
    $genero = $_POST['genero'] ?? '';
    $evento = $_POST['concurso'] ?? ''; // En HTML se llama 'concurso', en BD 'evento'
    $agremiado = isset($_POST['agremiado']) && $_POST['agremiado'] == '1' ? 1 : 0;
    $miembro_id = !empty($_POST['miembro_id']) ? $_POST['miembro_id'] : null;

    if (empty($nombre) || empty($evento)) {
        throw new Exception('Faltan datos obligatorios');
    }

    // 3. Lógica de Adaptación (El Hack para la columna 'cancion')
    $cancion_data = '';

    if ($evento === 'canto') {
        $c1 = $_POST['cancion_eliminatoria'] ?? 'N/A';
        $c2 = $_POST['cancion_final'] ?? 'N/A';
        // Concatenamos para guardar ambos datos en un solo campo texto
        $cancion_data = "Eliminatoria: $c1 | Final: $c2";
    } elseif ($evento === 'baile') {
        $pareja = $_POST['pareja_nombre'] ?? 'Sin especificar';
        // Guardamos la pareja en el campo cancion
        $cancion_data = "Pareja: $pareja";
    } else {
        // Yardas u otros
        $cancion_data = "N/A";
    }

    // 4. Inserción Segura (Prepared Statements)
    $sql = "INSERT INTO participantes 
            (nombre, genero, area, evento, cancion, agremiado, miembro_id) 
            VALUES 
            (:nombre, :genero, :area, :evento, :cancion, :agremiado, :miembro_id)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
            ':nombre'    => $nombre,
            ':genero'    => $genero,
            ':area'      => $area,
            ':evento'    => $evento,
            ':cancion'   => $cancion_data,
            ':agremiado' => $agremiado,     // 1 o 0
            ':miembro_id'=> $miembro_id     // ID o NULL
    ]);
    $id_nuevo = $pdo->lastInsertId();

    // 5. Respuesta de Éxito
    echo json_encode([
            'success' => true,
            'id' => $id_nuevo,
            'message' => 'Registro exitoso. Descargando tu ticket...'
    ]);

} catch (Exception $e) {
    // Respuesta de Error
    http_response_code(400); // Bad Request
    echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
    ]);
}