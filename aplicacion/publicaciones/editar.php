<?php

use App\Manejadores\SesionProtegida;
use App\Modelos\Publicacion;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';
SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['administrador', 'lider']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
    $resumen = filter_input(INPUT_POST, 'resumen', FILTER_SANITIZE_SPECIAL_CHARS);
    $contenido = filter_input(INPUT_POST, 'contenido');
    $tipo = filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_SPECIAL_CHARS);
    $importante = filter_input(INPUT_POST, 'importante', FILTER_VALIDATE_BOOLEAN);
    $fechaLimite = filter_input(INPUT_POST, 'fecha_limite');
    $nombreArchivo = null;

    try {
        Publicacion::actualizar(
                id: $id,
                titulo: $titulo,
                resumen: $resumen,
                contenido: $contenido,
                tipo: $tipo,
                imagen: $_FILES['archivo'] ?? null,
                expiracion: $fechaLimite,
                importante: $importante,
        );

        $mensaje = 'Publicación actualizada correctamente.';

        header("Location: ?id=$id&mensaje=$mensaje");
        exit();
    } catch (\Throwable $th) {
        $error = $th->getMessage();

        header("Location: ?id=$id&error=$error");
        exit();
    }
}


$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$publicacion = Publicacion::buscarPorId($id);

$datos = [
        'publicacion' => $publicacion,
        'mensaje' => filter_input(INPUT_GET, 'mensaje', FILTER_SANITIZE_SPECIAL_CHARS),
        'error' => filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS)
];

ServicioLatte::renderizar(__DIR__ . '/editar.latte', $datos);