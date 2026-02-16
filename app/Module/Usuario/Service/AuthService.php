<?php

namespace App\Module\Usuario\Service;

use App\Module\Usuario\Repository\UsuarioRepository;

final class AuthService
{
    public function __construct(
        private readonly UsuarioRepository $repository,
    ) {}

    public function login(string $email, string $password)
    {
        $usuario = $this->repository->buscarUsuarioPorEmail($email);

        if (!$usuario) {
            return null;
        }

        if (!password_verify($password, $usuario["passwordHash"])) {
            return null;
        }

        // Aquí podrías generar un token JWT o una sesión
        return $usuario;
    }
}
