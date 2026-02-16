<?php

use App\Configuracion\MysqlConexion;
use App\Fabricas\FabricaLatte;
use App\Manejadores\SesionProtegida;
use Dompdf\Dompdf;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['administrador', 'lider']);

$filtrar = false;

$filtrar = (isset($_GET['filtrar']) && $_GET['filtrar'] === 'true');

$sql = "
SELECT 
    m.id AS miembro_id,
    m.nombre,
    m.apellidos,
    m.departamento,
    m.telefono,
    m.fecha_ingreso,
    p.id AS propuesta_id,
    pc.contenido,
    pc.tipo
FROM miembros m
" . ($filtrar
                ? "INNER JOIN propuestas p ON m.id = p.miembro_id"
                : "LEFT JOIN propuestas p ON m.id = p.miembro_id") . "
LEFT JOIN propuestas_contenido pc ON p.id = pc.propuesta_id
ORDER BY m.id, p.id;
";



$pdo = MysqlConexion::conexion();

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


// 🔹 Reorganizamos los datos por miembro
$miembros = [];
foreach ($rows as $row) {
    $id = $row['miembro_id'];
    if (!isset($miembros[$id])) {
        $miembros[$id] = [
                'nombre' => $row['nombre'],
                'apellidos' => $row['apellidos'],
                'departamento' => $row['departamento'],
                'telefono' => $row['telefono'],
                'fecha_ingreso' => $row['fecha_ingreso'],
                'propuestas' => [],
        ];
    }

    if ($row['propuesta_id']) {
        $miembros[$id]['propuestas'][] = [
                'contenido' => $row['contenido'],
                'tipo' => $row['tipo'],
        ];
    }
}

// 🔹 Renderizamos con Latte
$latte = FabricaLatte::obtenerInstancia();
$html = $latte->renderToString(__DIR__ . '/pdf.latte', [
        'miembros' => $miembros,
]);

// 🔹 Generar PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 🔹 Salida del PDF (en el navegador)
$dompdf->stream('reporte_miembros.pdf', ['Attachment' => false]);