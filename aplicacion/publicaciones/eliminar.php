<?php

use App\Manejadores\SesionProtegida;
use App\Modelos\Publicacion;

require_once __DIR__ . '/../../src/configuracion.php';
SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['administrador', 'lider']);

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$path = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_SPECIAL_CHARS);
try {
    Publicacion::eliminar($id);
    header("Location: /aplicacion/?mensaje=Publicación eliminada correctamente.");
    exit();
} catch (\Throwable $th) {
    $error = $th->getMessage();
    header("Location: /aplicacion/?error=$error");
    exit();
}

