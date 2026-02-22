<?php

namespace App\Module\Usuario\Repository;

use App\Infrastructure\Persistence\BaseRepository;
use App\Module\Auth\DTO\UserAuthContextDTO;
use App\Module\Auth\DTO\UserAuthDTO;
use App\Module\Auth\Enum\RolEnum;
use App\Module\Usuario\DTO\UserProfileDTO;
use App\Module\Usuario\DTO\UsuarioSimpleDTO;
use App\Module\Usuario\Entity\Usuario;
use DateTimeImmutable;

use function array_map;

final class UsuarioRepository extends BaseRepository
{
    public function findAuthByEmail(string $email): ?UserAuthDTO
    {
        $stmt = $this->pdo->prepare(
            "SELECT usuario_id, email, password_hash, rol, activo, ultimo_ingreso FROM usuarios WHERE email = :email",
        );
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        return new UserAuthDTO(
            id: $result["usuario_id"],
            email: $result["email"],
            passwordHash: $result["password_hash"],
            rol: RolEnum::tryFrom($result["rol"]),
            active: (bool) $result["activo"],
            ultimoIngreso: $result["ultimo_ingreso"]
                ? new DateTimeImmutable($result["ultimo_ingreso"])
                : null,
        );
    }

    public function findAuthById(int $id): ?UserAuthDTO
    {
        $stmt = $this->pdo->prepare(
            "select usuario_id, email, password_hash, rol, activo, ultimo_ingreso from usuarios where usuario_id = :id",
        );

        $stmt->bindParam(":id", $id);
        $stmt->execute();

        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        return new UserAuthDTO(
            id: $result["usuario_id"],
            email: $result["email"],
            passwordHash: $result["password_hash"],
            rol: RolEnum::tryFrom($result["rol"]),
            active: (bool) $result["activo"],
            ultimoIngreso: $result["ultimo_ingreso"]
                ? new DateTimeImmutable($result["ultimo_ingreso"])
                : null,
        );
    }

    public function findAuthContextById(int $id): ?UserAuthContextDTO
    {
        $stmt = $this->pdo->prepare(
            "select usuario_id, nombre, apellidos, email, rol from usuarios where usuario_id = :id",
        );

        $stmt->execute([":id" => $id]);

        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        return new UserAuthContextDTO(
            id: $result["usuario_id"],
            nombre: $result["nombre"],
            apellidos: $result["apellidos"],
            email: $result["email"],
            rol: RolEnum::tryFrom($result["rol"]),
        );
    }

    public function findProfileById(int $id): ?UserProfileDTO
    {
        return new UserProfileDTO();
    }

    /**
     * @return UsuarioSimpleDTO[]
     */
    public function listado(): array
    {
        $stmt = $this->pdo->query(
            "select usuario_id, nombre, apellidos, email, rol, activo, departamento from usuarios",
        );
        $result = $stmt->fetchAll();

        return array_map(
            fn($row) => new UsuarioSimpleDTO(
                id: $row["usuario_id"],
                nombre: $row["nombre"],
                apellidos: $row["apellidos"],
                email: $row["email"],
                rol: RolEnum::tryFrom($row["rol"]) ?? RolEnum::Agremiado,
                activo: (bool) $row["activo"],
                departamento: $row["departamento"],
            ),
            $result,
        );
    }

    public function registrarUsuario(Usuario $usuario): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO usuarios (email, password_hash, rol, curp, nombre, apellidos, 
                  fecha_nacimiento, direccion, telefono, foto, banco_nombre, 
                  clabe_interbancaria, cuenta_bancaria, categoria, departamento, 
                  nss, salario_quincenal, fecha_ingreso_laboral)
         VALUES (:email, :password_hash, :rol, :curp, :nombre, :apellidos, 
                 :fecha_nacimiento, :direccion, :telefono, :foto, :banco_nombre, 
                 :clabe_interbancaria, :cuenta_bancaria, :categoria, :departamento,
                 :nss, :salario_quincenal, :fecha_ingreso_laboral)",
        );

        $stmt->execute([
            ":email" => $usuario->email,
            ":password_hash" => $usuario->passwordHash,
            ":rol" => $usuario->rol->value,
            ":curp" => $usuario->curp,
            ":nombre" => $usuario->nombre,
            ":apellidos" => $usuario->apellidos,
            ":fecha_nacimiento" => $usuario->fechaNacimiento?->format("Y-m-d"),
            ":direccion" => $usuario->direccion,
            ":telefono" => $usuario->telefono,
            ":foto" => $usuario->foto,
            ":banco_nombre" => $usuario->bancoNombre,
            ":clabe_interbancaria" => $usuario->clabeInterbancaria,
            ":cuenta_bancaria" => $usuario->cuentaBancaria,
            ":categoria" => $usuario->categoria,
            ":departamento" => $usuario->departamento,
            ":nss" => $usuario->nss,
            ":salario_quincenal" => $usuario->salarioQuincenal,
            ":fecha_ingreso_laboral" => $usuario->fechaIngresoLaboral?->format(
                "Y-m-d",
            ),
        ]);
    }

    // Relativo a la autenticación
    public function updatePassword(int $id, string $newPassword): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios SET password_hash = :password WHERE usuario_id = :id",
        );
        $stmt->execute([
            ":password" => $newPassword,
            ":id" => $id,
        ]);
    }
}
