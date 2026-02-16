<?php

namespace App\Modelos;

use App\Configuracion\MysqlConexion;
use PDO;

final class Publicacion
{
    static function agregar(
            string              $titulo,
            string              $resumen,
            string              $contenido,
            int                 $fk_miembro,
            string              $tipo,
            ?array              $imagen,
            ?\DateTimeImmutable $expiracion,
            bool                $importante = false,
    )
    {
        $conn = MysqlConexion::conexion();

        $sql = "INSERT INTO publicaciones (titulo, resumen, contenido, imagen, expiracion, fk_miembro, tipo, importante) VALUES (
        :titulo,
        :resumen,
        :contenido,
        :imagen,
        :expiracion,
        :fk_miembro,
        :tipo,
        :importante
    )";

        $nombreArchivo = self::getArchivo($imagen);

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':titulo', $titulo);
        $stmt->bindValue(':resumen', $resumen);
        $stmt->bindValue(':contenido', $contenido);
        $stmt->bindValue(':imagen', $nombreArchivo);
        $stmt->bindValue(':expiracion', $expiracion?->format('Y-m-d H:i:s'));
        $stmt->bindValue(':fk_miembro', $fk_miembro);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':importante', $importante, \PDO::PARAM_BOOL);

        if (!$stmt->execute()) {
            // Si hubo un error al ejecutar la consulta, eliminar el archivo subido si existe
            if ($nombreArchivo) {
                $rutaArchivo = __DIR__ . '/../../archivos/subidos/publicaciones/' . $nombreArchivo;
                if (file_exists($rutaArchivo)) {
                    unlink($rutaArchivo);
                }
            }
            throw new \RuntimeException('Error al registrar la publicación.');
        }

    }

    public static function actualizar(
            ?int                $id,
            ?string             $titulo,
            ?string             $resumen,
            ?string             $contenido,
            ?string             $tipo,
            ?array              $imagen,
            ?\DateTimeImmutable $expiracion,
            ?bool               $importante = false,
    )
    {
        $publicacion = self::buscarPorId($id);
        if (!$publicacion) {
            throw new \InvalidArgumentException('La publicación no existe.');
        }

        $conn = MysqlConexion::conexion();
        $sql = "UPDATE publicaciones SET
            titulo = :titulo,
            resumen = :resumen,
            contenido = :contenido,
            imagen = :imagen,
            expiracion = :expiracion,
            tipo = :tipo,
            importante = :importante
            WHERE id = :id
        ";

        $nombreArchivo = self::getArchivo($imagen);

        if ($nombreArchivo === null) {
            // Mantener la imagen existente si no se subió una nueva
            $nombreArchivo = $publicacion['imagen'];
        }

        // si el nombreArchivo es diferente al de la publicacion, eliminar el archivo anterior
        if ($nombreArchivo !== $publicacion['imagen'] && $publicacion['imagen']) {
            $rutaArchivoAnterior = __DIR__ . '/../../archivos/subidos/publicaciones/' . $publicacion['imagen'];
            if (file_exists($rutaArchivoAnterior)) {
                unlink($rutaArchivoAnterior);
            }
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':titulo', $titulo);
        $stmt->bindValue(':resumen', $resumen);
        $stmt->bindValue(':contenido', $contenido);
        $stmt->bindValue(':imagen', $nombreArchivo);
        $stmt->bindValue(':expiracion', $expiracion?->format('Y-m-d H:i:s'));
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':importante', $importante, \PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $id);
        if (!$stmt->execute()) {
            throw new \RuntimeException('Error al actualizar la publicación.');
        }
    }

    public static function eliminar(?int $id)
    {
        $publicacion = self::buscarPorId($id);
        if (!$publicacion) {
            throw new \InvalidArgumentException('La publicación no existe.');
        }

        $conn = MysqlConexion::conexion();
        $sql = "DELETE FROM publicaciones WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id);
        if (!$stmt->execute()) {
            throw new \RuntimeException('Error al eliminar la publicación.');
        }

        // Eliminar el archivo de imagen asociado si existe
        if ($publicacion['imagen']) {
            $rutaArchivo = __DIR__ . '/../../archivos/subidos/publicaciones/' . $publicacion['imagen'];
            if (file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }
        }
    }

    public static function buscarPorId(
            ?int $id,
    )
    {
        $conn = MysqlConexion::conexion();
        $query = $conn->prepare("SELECT * FROM publicaciones WHERE id = :id");
        $query->bindValue(':id', $id);
        $query->execute();
        return $query->fetch(\PDO::FETCH_ASSOC);
    }

    public static function buscarPorTipo(
            string $tipo,
    )
    {
        $conn = MysqlConexion::conexion();
        $query = $conn->prepare("SELECT * FROM publicaciones WHERE tipo = :tipo ORDER BY id DESC");
        $query->bindValue(':tipo', $tipo);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function buscarAvisosActivosRecientes()
    {
        $conn = MysqlConexion::conexion();
        $sql = "SELECT * FROM publicaciones 
WHERE (expiracion IS NULL OR expiracion >= CURDATE())
AND tipo = 'aviso'
ORDER BY fecha DESC;";
        $resultado = $conn->query($sql);
        return $resultado->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function obtenerAvisos()
    {
        return self::buscarPorTipo('aviso');
    }

    public static function obtenerNoticias()
    {
        return self::buscarPorTipo('noticia');
    }

    public static function obtenerGestiones()
    {
        return self::buscarPorTipo('gestiones');
    }

    public static function ultimasPublicaciones(int $limite = 5)
    {
        $query = MysqlConexion::conexion()->prepare(
                "SELECT id, titulo, resumen, imagen 
            FROM publicaciones 
            WHERE expiracion IS NULL OR expiracion >= CURDATE() 
            ORDER BY fecha DESC 
            LIMIT :limite"
        );
        $query->bindValue(':limite', $limite, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function ultimasPublicacionesPorTipo(string $tipo, int $limite = 5)
    {
        $query = MysqlConexion::conexion()->prepare(
                "SELECT id, titulo, resumen, imagen 
            FROM publicaciones 
            WHERE (expiracion IS NULL OR expiracion >= CURDATE()) AND tipo = :tipo
            ORDER BY fecha DESC 
            LIMIT :limite"
        );
        $query->bindValue(':tipo', $tipo);
        $query->bindValue(':limite', $limite, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array|null $imagen
     * @return string|null
     */
    public static function getArchivo(?array $imagen): ?string
    {
        if (isset($imagen['error']) && $imagen['error'] == UPLOAD_ERR_OK) {
            $directorio = __DIR__ . '/../../archivos/subidos/publicaciones/';
            if (!is_dir($directorio) && !mkdir($directorio, 0777, true) && !is_dir($directorio)) {
                throw new \RuntimeException('No se pudo crear el directorio de subida.');
            } else {
                $nombreOriginal = basename($imagen['name']);
                $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
                $permitidos = ['jpg', 'jpeg', 'png'];
                $tamanoMax = 2 * 1024 * 1024; // 2MB

                if (!in_array($extension, $permitidos, true)) {
                    throw new \InvalidArgumentException('Tipo de archivo no permitido.');
                } elseif ((int)$imagen['size'] > $tamanoMax) {
                    throw new \InvalidArgumentException('El archivo excede el tamaño máximo permitido (2MB).');
                } else {
                    // Generar nombre único y seguro
                    $nombreArchivo = uniqid('img_', true) . '.' . $extension;
                    $rutaDestino = $directorio . $nombreArchivo;
                    if (!move_uploaded_file($imagen['tmp_name'], $rutaDestino)) {
                        throw new \RuntimeException('Error al guardar el archivo.');
                    }
                }
            }
        } else {
            $nombreArchivo = null;
        }
        return $nombreArchivo;
    }
}