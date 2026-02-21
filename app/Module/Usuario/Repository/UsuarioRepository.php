<?php

namespace App\Module\Usuario\Repository;

use App\Infrastructure\Persistence\BaseRepository;
use App\Module\Usuario\DTO\AutenticacionLogDTO;
use App\Module\Usuario\DTO\UserAuthDTO;

use App\Module\Usuario\DTO\UsuarioSimpleDTO;

use App\Module\Usuario\Entity\RolEnum;

use App\Module\Usuario\Entity\Usuario;

use Faker\Core\Uuid;

use function array_map;
use function DI\string;

final class UsuarioRepository extends BaseRepository
{
    public function buscarUsuarioPorEmail(string $email): ?UserAuthDTO
    {
        $stmt = $this->pdo->prepare(
            "SELECT usuario_id, email, password_hash, rol, activo FROM usuarios WHERE email = :email",
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
            password: $result["password_hash"],
            rol: $result["rol"],
            active: (bool) $result["activo"],
        );
    }

    /**
     * @return UsuarioSimpleDTO[]
     */
    public function listado(): array
    {
        $stmt = $this->pdo->query(
            "select usuario_id, nombre, apellidos, email, departamento from usuarios",
        );
        $result = $stmt->fetchAll();

        return array_map(
            fn($row) => new UsuarioSimpleDTO(
                id: $row["usuario_id"],
                nombre: $row["nombre"],
                apellidos: $row["apellidos"],
                email: $row["email"],
                rol: RolEnum::tryFrom($row["rol"]),
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

    public function registrarEventoUsuario(
        AutenticacionLogDTO $autenticacionLog,
    ) {
        $stmt = $this->pdo->prepare("insert into");
    }

    private function generateUuid() {}

    private function readUuid() {}
}
