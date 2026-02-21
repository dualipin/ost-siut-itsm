<?php

namespace App\Module\Usuario\Service;

use App\Module\Usuario\DTO\UserAuthDTO;
use App\Module\Usuario\Repository\UsuarioRepository;

final readonly class AuthService
{
    public function __construct(private UsuarioRepository $repository) {}

    public function login(string $email, string $password): ?UserAuthDTO
    {
        $usuario = $this->repository->buscarUsuarioPorEmail($email);

        if (!$usuario) {
            return null;
        }

        if (!password_verify($password, $usuario["passwordHash"])) {
            return null;
        }

        return $usuario;
    }
}
