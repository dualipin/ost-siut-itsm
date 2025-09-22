<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/configuracion.php';

use App\Manejadores\Sesion;
use App\Modelos\Cuenta;
use App\Servicios\ServicioLatte;

$error = null;

// Redirigir si ya hay sesión activa
if (Sesion::sesionAbierta() !== null) {
    header('Location: /aplicacion/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['contra'] ?? '';
    $tokenEnviado = $_POST['csrf_token'] ?? '';

    // 1. Validar CSRF
    if (!Sesion::validarCSRFToken($tokenEnviado)) {
        $error = 'Error de validación. Intente nuevamente.';
        error_log("Fallo CSRF en login para usuario: $usuario");
    } else {
        // 2. Verificar credenciales
        $miembro = Cuenta::iniciarSesion($usuario, $password);

        if ($miembro !== null) {
            // 3. Usar la clase Sesion para iniciar sesión de forma segura
            Sesion::iniciarSesion($miembro->getId());

            header('Location: /aplicacion/index.php');
            exit;
        } else {
            $error = 'Credenciales inválidas.';
            error_log("Intento de login fallido: $usuario");
        }
    }
}

// Renderizar plantilla con CSRF ya gestionado por la clase Sesion
ServicioLatte::renderizar(__DIR__ . '/../plantillas/cuenta/login.latte', [
        'error' => $error,
        'usuario' => $_POST['usuario'] ?? '',
]);
