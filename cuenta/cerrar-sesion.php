<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/configuracion.php';

use App\Manejadores\Sesion;

Sesion::cerrarSesion();

// Redirigir a la página de login (o a la raíz si prefieres)
header('Location: /cuenta/login.php');
exit;
