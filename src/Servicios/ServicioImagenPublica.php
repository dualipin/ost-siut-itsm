<?php

namespace App\Servicios;

class ServicioImagenPublica
{
    public static function cargarImagenPublica(
            array  $archivo,
            string $rutaDestino,
            array  $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'],
            int    $tamanioMaximo = 2 * 1024 * 1024 // 2MB
    )
    {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Error al subir el archivo.');
        }

        if (!in_array($archivo['type'], $tiposPermitidos)) {
            throw new \RuntimeException('Tipo de archivo no permitido.');
        }

        if ($archivo['size'] > $tamanioMaximo) {
            throw new \RuntimeException('El archivo es demasiado grande.');
        }

        $nombreArchivo = uniqid('img_', true) . '.' . pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $rutaCompleta = rtrim($rutaDestino, '/') . '/' . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
            throw new \RuntimeException('Error al mover el archivo subido.');
        }

        return $nombreArchivo;
    }

}