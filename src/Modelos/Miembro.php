<?php

namespace App\Modelos;

use App\Configuracion\MysqlConexion;
use App\Entidades\EntidadMiembro;
use PDO;
use Throwable;

final class Miembro
{
    /**
     * @throws Throwable
     */
    public static function registrarMiembro(EntidadMiembro $miembro): int
    {
        $sqlUsuario = "INSERT INTO usuarios (correo, contra, rol) VALUES (?, ?, ?)";
        $sqlMiembro = "INSERT INTO miembros (
                           nombre, 
                           apellidos,
                           direccion,
                           telefono,
                           categoria,
                           departamento,
                           nss,
                           curp,
                           fecha_ingreso,
                           fecha_nacimiento,
                           fk_usuario) 
                       VALUES (?,?,?,?,?,?,?,?,?,?,?)";

        $con = MysqlConexion::conexion();
        $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $con->beginTransaction();

            // Insert usuario
            $stmtUsuario = $con->prepare($sqlUsuario);
            $stmtUsuario->execute([
                    $miembro->getCorreo(),
                    password_hash($miembro->getContra(), PASSWORD_BCRYPT),
                    $miembro->getRol()
            ]);

            $idUsuario = (int)$con->lastInsertId();

            // Insert miembro
            $stmtMiembro = $con->prepare($sqlMiembro);
            $stmtMiembro->execute([
                    $miembro->getNombre(),
                    $miembro->getApellidos(),
                    $miembro->getDireccion(),
                    $miembro->getTelefono(),
                    $miembro->getCategoria(),
                    $miembro->getDepartamento(),
                    $miembro->getNss(),
                    $miembro->getCurp(),
                    $miembro->getFechaIngreso()?->format('Y-m-d'),
                    $miembro->getFechaNacimiento()?->format('Y-m-d'),
                    $idUsuario
            ]);

            $con->commit();

            return $idUsuario;
        } catch (Throwable $e) {
            $con->rollBack();
            throw $e;
        }
    }


    public static function buscarMiembroId(int $idUsuario): ?EntidadMiembro
    {
        $sql = "SELECT 
                    m.id AS miembro_id,
                    m.nombre,
                    m.apellidos,
                    m.direccion,
                    m.telefono,
                    m.categoria,
                    m.departamento,
                    m.nss,
                    m.curp,
                    m.fecha_ingreso,
                    m.fecha_nacimiento,
                    u.id AS usuario_id,
                    u.correo,
                    u.rol
                FROM miembros m
                JOIN usuarios u ON m.fk_usuario = u.id
                WHERE u.id = ?";

        $con = MysqlConexion::conexion();
        $stmt = $con->prepare($sql);
        $stmt->execute([$idUsuario]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila) {
            try {
                return new EntidadMiembro(
                        nombre: $fila['nombre'],
                        apellidos: $fila['apellidos'],
                        id: (int)$fila['miembro_id'],
                        direccion: $fila['direccion'],
                        telefono: $fila['telefono'],
                        correo: $fila['correo'],
                        categoria: $fila['categoria'],
                        departamento: $fila['departamento'],
                        nss: $fila['nss'],
                        curp: $fila['curp'],
                        fechaNacimiento: $fila['fecha_nacimiento'] ? new \DateTimeImmutable($fila['fecha_nacimiento']) : null,
                        fechaIngreso: $fila['fecha_ingreso'] ? new \DateTimeImmutable($fila['fecha_ingreso']) : null,
                        rol: $fila['rol'],
                        userId: $fila['usuario_id']
                );
            } catch (\DateMalformedStringException $e) {
                error_log($e->getMessage());
            }
        }
        return null;
    }
}
