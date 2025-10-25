<?php
declare(strict_types=1);

require_once __DIR__ . '/src/configuracion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['name'] ?? '');
    $telefono = trim($_POST['phone'] ?? '');
    $correo = trim($_POST['email'] ?? '');
    $asunto = trim($_POST['subject'] ?? '');
    $mensaje = trim($_POST['message'] ?? '');


    if (empty($nombre) || empty($correo) || empty($mensaje) || empty($asunto) || empty($telefono)) {
        echo '
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h2>Error</h2>
            <p>Todos los campos son obligatorios. Por favor, completa el formulario.</p>
    </div>
    ';
        exit();
    }


    try {
        $sEmail = new \App\Servicios\ServicioCorreo();

        $sEmail->enviarCorreoContacto(
                enviarA: 'martin.msr1304@gmail.com',
                asunto: $asunto,
                mensaje: $mensaje,
                nombre: $nombre,
                telefono: $telefono
        );


    } catch (Exception $e) {
        echo "
        <div class='alert-danger alert alert-dismissible fade show' role='alert'>
        <h2>Error</h2>
        <p>Hubo un error al enviar tu mensaje. Por favor, inténtalo de nuevo más tarde.</p>
        </div>
        ";
    }


    echo "
    <div class='alert-success alert alert-dismissible fade show' role='alert'>
        <h2>¡Mensaje enviado con éxito!</h2>
        <p>Gracias, $nombre. Hemos recibido tu mensaje y nos pondremos en contacto contigo pronto.</p>
</div>
    ";


} else {
    http_response_code(405);
    header('Location: /#contact');
    exit();
}