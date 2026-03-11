<?php
require_once __DIR__ . '/../../../src/configuracion.php';

use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;

SesionProtegida::proteger();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id_doc'])) {
        $documentoId = (int)$_POST['id_doc'];
        $conn = MysqlConexion::conexion();

        try {
            $conn->beginTransaction();

            $adjuntoStmt = $conn->prepare('SELECT adjunto FROM documentos_tramites_ante_autoridades WHERE id = ?');
            $adjuntoStmt->execute([$documentoId]);
            $resultado = $adjuntoStmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado && !empty($resultado['adjunto'])) {
                $rutaArchivo = realpath(__DIR__ . '/../../../privado/archivos/repositorios/tramite-ante-autoridades/' . $resultado['adjunto']);
                if ($rutaArchivo && file_exists($rutaArchivo)) {
                    unlink($rutaArchivo);
                }
            }

            $stmt = $conn->prepare('DELETE FROM documentos_tramites_ante_autoridades WHERE id = ?');
            $res = $stmt->execute([$documentoId]);

            if ($res) {
                $conn->commit();
                http_response_code(200);
                header('Location: index.php?mensaje=' . urlencode('Eliminado con éxito'));
            } else {
                $conn->rollBack();
                http_response_code(500);
                header('Location: index.php?error=' . urlencode('Error al eliminar'));
            }
            exit;
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            http_response_code(500);
            header('Location: index.php?error=' . urlencode('Error interno: ' . $e->getMessage()));
            exit;
        }
    }
} else {
    http_response_code(405);
    header('Location: index.php');
    exit('Método no permitido');
}