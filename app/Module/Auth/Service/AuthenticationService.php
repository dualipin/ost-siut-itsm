<?php

namespace App\Module\Auth\Service;

use App\Module\Auth\DTO\AuthLogDTO;
use App\Module\Auth\DTO\UserAuthDTO;
use App\Module\Auth\Enum\AuthLogActionEnum;
use App\Module\Auth\Repository\AuthenticationRepository;
use App\Module\Usuario\Repository\UsuarioRepository;

/**
 * Servicio de autenticación
 */
final class AuthenticationService
{
    private ?UserAuthDTO $currentUser = null;

    public function __construct(
        private readonly UsuarioRepository $userRepo,
        private readonly AuthenticationRepository $authRepo,
    ) {}

    /**
     * Auténtica un usuario con email y contraseña
     */
    public function authenticate(
        string $email,
        string $password,
        string $ipAddress = null,
        string $userAgent = null,
    ): bool {
        $user = $this->userRepo->findAuthByEmail($email);

        if (!$user || !$user->active) {
            $this->authRepo->saveAuthLog(
                new AuthLogDTO(
                    action: AuthLogActionEnum::LoginAttempt,
                    success: false,
                    email: $email,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    errorMessage: "Usuario no encontrado o inactivo",
                ),
            );
            return false;
        }

        if (!password_verify($password, $user->passwordHash)) {
            $this->authRepo->saveAuthLog(
                new AuthLogDTO(
                    action: AuthLogActionEnum::LoginAttempt,
                    success: false,
                    usuarioId: (int) $user->id,
                    email: $email,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    errorMessage: "Contraseña incorrecta",
                ),
            );
            return false;
        }

        // Actualizar último login
        $this->authRepo->updateLastLogin($user->id);
        $this->currentUser = $user;
        $this->authRepo->saveAuthLog(
            new AuthLogDTO(
                action: AuthLogActionEnum::LoginAttempt,
                success: true,
                usuarioId: (int) $user->id,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );
        return true;
    }

    /**
     * Registra un nuevo usuario
     */
    public function register(
        string $email,
        string $password,
        string $nombre,
        string $apellidos,
    ): int {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $user = new User(
            id: 0,
            email: $email,
            password: $hashedPassword,
            nombre: $nombre,
            apellidos: $apellidos,
        );

        return $this->userRepository->save($user);
    }

    /**
     * Obtiene el usuario actualmente autenticado
     */
    public function getCurrentUser(): ?User
    {
        return $this->currentUser;
    }

    /**
     * Establece el usuario actual (usado después de cargar de sesión)
     */
    public function setCurrentUser(?User $user): void
    {
        $this->currentUser = $user;
    }

    /**
     * Verifica si hay un usuario autenticado
     */
    public function isAuthenticated(): bool
    {
        return $this->currentUser !== null;
    }

    /**
     * Verifica si el usuario actual tiene un rol específico
     */
    public function hasRole(string $role): bool
    {
        if (!$this->currentUser) {
            return false;
        }
        return $this->currentUser->hasRole($role);
    }

    /**
     * Verifica si el usuario actual tiene alguno de los roles especificados
     */
    public function hasAnyRole(array $roles): bool
    {
        if (!$this->currentUser) {
            return false;
        }
        return $this->currentUser->hasAnyRole($roles);
    }

    /**
     * Verifica si el usuario actual tiene todos los roles especificados
     */
    public function hasAllRoles(array $roles): bool
    {
        if (!$this->currentUser) {
            return false;
        }
        return $this->currentUser->hasAllRoles($roles);
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logout(): void
    {
        $this->currentUser = null;
    }

    /**
     * Valida que un email sea válido
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Verifica si un email ya está registrado
     */
    public function emailExists(string $email): bool
    {
        return $this->userRepo->findAuthByEmail($email) !== null;
    }
}
