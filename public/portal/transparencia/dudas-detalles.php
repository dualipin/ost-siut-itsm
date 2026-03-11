<?php

require_once __DIR__ . '/../../src/configuracion.php';

$id = $_GET['id'] ?? null;
if ($id === null) {
    header('Location: dudas.php');
    exit;
}

$pdo = \App\Configuracion\MysqlConexion::conexion();
$stmt = $pdo->prepare('SELECT id, nombre, correo, duda, fecha FROM dudas_transparencia WHERE id = :id');
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$duda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$duda) {
    header('Location: dudas.php');
    exit;
}

$respuestas = $pdo->prepare(
        'SELECT id, respuesta, fecha from dudas_transparencia_respuestas where duda_id = :duda_id ORDER BY fecha'
);
$respuestas->bindParam(':duda_id', $id, PDO::PARAM_INT);
$respuestas->execute();
$respuestas = $respuestas->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respuesta = $_POST['respuesta'] ?? '';
    $stmt = $pdo->prepare(
            'INSERT INTO dudas_transparencia_respuestas (duda_id, respuesta) 
        VALUES (:duda_id, :respuesta)'
    );
    $stmt->bindParam(':duda_id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':respuesta', $respuesta, PDO::PARAM_STR);
    $stmt->execute();


    try {
        $mail = \App\Fabricas\FabricaCorreo::instancia();
        $mail->setFrom($_ENV['MAIL_USERNAME'], 'Equipo de Transparencia');
        $mail->Subject = 'Respuesta a su duda de transparencia';
        $mail->Body = "Hola " . htmlspecialchars($duda['nombre']) . ",<br><br>" .
                "Hemos respondido a su duda de transparencia:<br><br>" .
                "<strong>Su duda:</strong><br>" . nl2br(htmlspecialchars($duda['duda'])) . "<br><br>" .
                "<strong>Nuestra respuesta:</strong><br>" . nl2br(htmlspecialchars($respuesta)) . "<br><br>" .
                "Saludos cordiales,<br>" .
                "Equipo de Transparencia";
        $mail->isHTML(true);
        $mail->addAddress($duda['correo'], $duda['nombre']);
        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Error al crear instancia de correo: " . $e->getMessage());
    }


    // Redirigir a la misma página para evitar reenvío de formulario
    header("Location: dudas-detalles.php?id=" . urlencode($id));
    exit;
}

$datos = [
        'duda' => $duda,
        'respuestas' => $respuestas,
];

\App\Servicios\ServicioLatte::renderizar(__DIR__ . '/dudas-detalles.latte', $datos);