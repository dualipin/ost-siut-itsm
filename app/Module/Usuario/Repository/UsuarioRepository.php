<?php

namespace App\Module\Usuario\Repository;

use App\Infrastructure\Persistence\BaseRepository;
use App\Module\Usuario\DTO\UserAuthDTO;

use App\Module\Usuario\DTO\UsuarioSimpleDTO;

use App\Module\Usuario\Entity\RolEnum;

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
}
