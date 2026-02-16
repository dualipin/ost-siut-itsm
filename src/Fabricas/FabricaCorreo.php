<?php

namespace App\Fabricas;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class FabricaCorreo
{
    private static ?PHPMailer $mail = null;


    /**
     * @throws Exception
     */
    public static function instancia(): PHPMailer
    {
        if (self::$mail === null) {
            $host = 'mail.siutitsm.com.mx';
            $username = 'contacto@siutitsm.com.mx';
            $port = 465; // Puerto SSL


            self::$mail = new PHPMailer(true);
            try {
                self::$mail->isSMTP();
                self::$mail->Host = $host;
                self::$mail->SMTPAuth = true;
                self::$mail->Username = $username;
                self::$mail->Password = $_ENV['MAIL_PASSWORD'];
                self::$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL (no STARTTLS)
                self::$mail->Port = $port; // Puerto SSL
                self::$mail->Timeout = 10;  // para evitar bloqueos largos
                return self::$mail;
            } catch (Exception $e) {
                throw new Exception('Error al crear la instancia de PHPMailer: ' . $e->getMessage());
            }
        }
        return self::$mail;
    }
}