<?php

use App\Manejadores\SesionProtegida;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['lider']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
}

echo 'correcto';