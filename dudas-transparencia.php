<?php

use App\Configuracion\MysqlConexion;
use App\Fabricas\FabricaCorreo;
use App\Fabricas\FabricaLatte;

require_once __DIR__ . '/src/configuracion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $mensaje = $_POST['mensaje'] ?? '';

    $pdo = MysqlConexion::conexion();
    $stmt = $pdo->prepare("INSERT INTO dudas_transparencia (nombre, correo, duda) VALUES (:nombre, :correo, :mensaje)");

    $r = $stmt->execute([
            ':nombre' => $nombre,
            ':correo' => $correo,
            ':mensaje' => $mensaje,
    ]);

    try {
        $mail = FabricaCorreo::instancia();
        $mail->setFrom($_ENV['MAIL_USERNAME'], 'Dudas de Transparencia SIUTITSM');
        $mail->addAddress($_ENV['MAIL_ADMIN'], 'Administrador SIUTITSM');
        $mail->isHTML(true);
        $mail->Subject = 'Nueva duda de transparencia recibida';
        $mail->Body = "
            <h3>Nueva duda de transparencia recibida</h3>
            <p><strong>Nombre:</strong> {$nombre}</p>
            <p><strong>Correo:</strong> {$correo}</p>
            <p><strong>Duda:</strong></p>
            <p>{$mensaje}</p>
        ";
        $mail->AltBody = "Nombre: {$nombre}\nCorreo: {$correo}\nDuda:\n{$mensaje}";
        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('Error al guardar la duda de transparencia: ' . $e->getMessage());
    }

    if (!$r) {
        header('Location: dudas-transparencia.php?error=1');
        exit;
    }
    // Aquí podrías agregar lógica para procesar el formulario,
    // como enviar un correo electrónico o guardar en una base de datos.

    // Redirigir o mostrar un mensaje de éxito
    header('Location: dudas-transparencia.php?success=1');
    exit;
}

$data = [
        'success' => isset($_GET['success']),
        'error' => isset($_GET['error']),
];

$latte = FabricaLatte::obtenerInstancia();
$latte->render(__DIR__ . '/dudas-transparencia.latte', $data);