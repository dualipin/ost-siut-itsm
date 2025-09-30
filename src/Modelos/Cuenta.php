<?php

namespace App\Modelos;

use App\Configuracion\MysqlConexion;
use App\Entidades\EntidadMiembro;
use DateTimeImmutable;
use PDO;
use PDOException;

final class Cuenta
{
    public static function iniciarSesion(string $correo, string $contra): ?EntidadMiembro
    {
        $correo = trim(strtolower($correo)); // normalizar correo

        try {
            $con = MysqlConexion::conexion();

            // Seleccionamos solo las columnas necesarias
            $stmt = $con->prepare(
                    "SELECT m.id, m.nombre, m.apellidos, m.direccion, m.telefono, m.categoria,
                        m.departamento, m.nss, m.curp, m.fecha_ingreso, m.fecha_nacimiento,
                        u.rol, u.contra AS hash_contra
                 FROM miembros m
                 LEFT JOIN usuarios u ON m.fk_usuario = u.id
                 WHERE u.correo = ?"
            );

            $stmt->execute([$correo]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila && password_verify($contra, $fila['hash_contra'])) {

                // Rehash automático si el hash está desactualizado
                if (password_needs_rehash($fila['hash_contra'], PASSWORD_BCRYPT)) {
                    $nuevoHash = password_hash($contra, PASSWORD_BCRYPT);
                    $update = $con->prepare('UPDATE usuarios SET contra = ? WHERE id = ?');
                    $update->execute([$nuevoHash, $fila['id']]);
                }

                // Convertimos fechas de forma segura
                $fechaNacimiento = null;
                if (!empty($fila['fecha_nacimiento'])) try {
                    $fechaNacimiento = new DateTimeImmutable($fila['fecha_nacimiento']);
                } catch (\Exception) {

                }

                $fechaIngreso = null;
                if (!empty($fila['fecha_ingreso'])) {
                    try {
                        $fechaIngreso = new DateTimeImmutable($fila['fecha_ingreso']);
                    } catch (\Exception) {

                    }
                }

                return new EntidadMiembro(
                        nombre: $fila['nombre'],
                        apellidos: $fila['apellidos'],
                        id: (int)$fila['id'],
                        direccion: $fila['direccion'] ?? null,
                        telefono: $fila['telefono'] ?? null,
                        correo: $correo,
                        categoria: $fila['categoria'] ?? null,
                        departamento: $fila['departamento'] ?? null,
                        nss: $fila['nss'] ?? null,
                        curp: $fila['curp'] ?? null,
                        fechaNacimiento: $fechaNacimiento,
                        fechaIngreso: $fechaIngreso,
                        rol: $fila['rol'] ?? 'agremiado'
                );
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            return null;
        }

        return null;
    }
}
