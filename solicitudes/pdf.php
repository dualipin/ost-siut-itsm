<?php

use App\Fabricas\FabricaLatte;
use Dompdf\Dompdf;


require_once __DIR__ . '/../src/configuracion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') header('Location: index.php');

$id_miembro = $_POST['miembro_id'] ?? null;

$existe = false;


$sql = "
SELECT 
    p.id AS propuesta_id,
    p.miembro_id,
    p.fecha,
    pc.id AS contenido_id,
    pc.contenido,
    pc.tipo
FROM propuestas AS p
INNER JOIN propuestas_contenido AS pc ON p.id = pc.propuesta_id
WHERE YEAR(p.fecha) = YEAR(CURDATE()) AND p.miembro_id = :id_miembro; 
";

$sqlMiembro = "
SELECT 
CONCAT(nombre, ' ', apellidos) AS nombre_completo
FROM miembros
WHERE id = :id_miembro;
";

$con = App\Configuracion\MysqlConexion::conexion();
try {
    $con->beginTransaction();
    $stmt = $con->prepare($sql);
    $stmt->execute(['id_miembro' => $id_miembro]);
    $propuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmtMiembro = $con->prepare($sqlMiembro);
    $stmtMiembro->execute(['id_miembro' => $id_miembro]);
    $miembro = $stmtMiembro->fetch(PDO::FETCH_ASSOC);
    $con->commit();

    if (count($propuestas) > 0) {
        $existe = true;
    }

} catch (Exception $e) {
    if ($con->inTransaction()) {
        $con->rollBack();
    }
    header('Location: index.php');
    die("Error al obtener las propuestas: " . $e->getMessage());
}

if (isset($_POST['verificar'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($existe)
        echo json_encode(['success' => true]);
    else
        echo json_encode(['success' => false]);
    exit;
}

if (!$existe) {
    header('Location: index.php');
    exit;
}

// hash de firma electrónica con el id del miembro y el id de la propuesta
$firmaElectronica = hash('sha256', $id_miembro . '-' . implode(',', array_column($propuestas, 'propuesta_id')));

//fecha de la propuesta de la base de datos
//$fechaPropuesta = date('Y-m-d');
$fechaPropuesta = $propuestas[0]['fecha'] ?? date('Y-m-d');

$latte = FabricaLatte::obtenerInstancia();

$html = $latte->renderToString(__DIR__ . '/pdf.latte', [
        'nombre' => $miembro['nombre_completo'] ?? 'Desconocido',
        'firmaElectronica' => $firmaElectronica,
        'fecha' => $fechaPropuesta,
        'propuestas' => $propuestas
]);


$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$options = $dompdf->getOptions();
$options->setIsRemoteEnabled(true);
$dompdf->setOptions($options);
$dompdf->render();
$dompdf->stream('propuesta.pdf', ['Attachment' => true]);
