<?php
// generar_ticket.php

use App\Fabricas\FabricaLatte;
use Dompdf\Dompdf;

// Asumo que tu autoloader y configuración están aquí
require_once __DIR__ . '/../src/configuracion.php';

// 1. Validar ID
$id = $_GET['id'] ?? null;
if (!$id) die("ID no proporcionado");

// 2. Conexión y Búsqueda
$con = App\Configuracion\MysqlConexion::conexion(); // Tu clase de conexión
// Si no usas esa clase, usa el PDO simple del ejemplo anterior:
// $con = new PDO("mysql:host=localhost;dbname=tu_bd", "root", "");

$sql = "SELECT * FROM participantes WHERE id = :id LIMIT 1";
$stmt = $con->prepare($sql);
$stmt->execute(['id' => $id]);
$participante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$participante) die("Participante no encontrado");

// 3. Generar Firma (Hash simple para validez visual)
$firmaElectronica = hash('sha256', $participante['id'] . $participante['nombre'] . 'NAVIDAD2025');

// 4. Renderizar con Latte
$latte = FabricaLatte::obtenerInstancia(); // O new \Latte\Engine;
$html = $latte->renderToString(__DIR__ . '/generar_ticket.latte', [
        'p' => $participante,
        'firma' => $firmaElectronica,
        'fecha_impresion' => date('d/m/Y H:i')
]);

// --- CORRECCIÓN AQUÍ ---

// 1. Crear instancia de opciones
$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', true);
//$options->set('defaultFont', 'Arial'); // Opcional si tienes problemas de fuentes

// 2. Instanciar Dompdf pasando las opciones
$dompdf = new Dompdf($options);

// 3. Cargar HTML y renderizar
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');

$dompdf->render();

// Forzar descarga con el nombre del participante
$filename = 'Ticket_Posada_' . preg_replace('/[^a-zA-Z0-9]/', '_', $participante['nombre']) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);