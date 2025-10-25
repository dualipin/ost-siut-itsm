<?php

namespace App\Servicios;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class ServicioCorreo
{

    /**
     * @throws Exception
     */
    public function enviarCorreoContacto(
            string $enviarA,
            string $asunto,
            string $mensaje,
            string $nombre,
            string $telefono
    ): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'mail.siutitsm.com.mx';
            $mail->SMTPAuth = true;
            $mail->Username = 'contacto@siutitsm.com.mx';
            $mail->Password = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL (no STARTTLS)
            $mail->Port = 465; // Puerto SSL
            $mail->Timeout = 10;  // para evitar bloqueos largos
            $mail->SMTPDebug = 2;   // solo durante pruebas

            $mail->setFrom('contacto@siutitsm.com.mx', 'Formulario de contacto');
            $mail->addAddress('martin.msr1304@gmail.com', 'Administrador del sitio');

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = "
        <h3>Nuevo mensaje del formulario de contacto</h3>
        <p><strong>Nombre:</strong> {$nombre}</p>
        <p><strong>Teléfono:</strong> {$telefono}</p>
        <p><strong>Mensaje:</strong></p>
        <p>{$mensaje}</p>
    ";

            $mail->AltBody = "Nombre: {$nombre}\nTeléfono: {$telefono}\nMensaje:\n{$mensaje}";

            $mail->send();
            echo "✅ Correo enviado correctamente";
        } catch (Exception $e) {
            echo "❌ Error: {$mail->ErrorInfo}";
            throw new Exception('Error al enviar el correo: ' . $e->getMessage());
        }

    }

}