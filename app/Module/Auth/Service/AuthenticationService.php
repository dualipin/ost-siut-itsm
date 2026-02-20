<?php

namespace App\Module\Auth\Service;

use App\Module\Auth\Entity\User;
use App\Module\Auth\Repository\UserRepositoryInterface;
use App\Module\Auth\Repository\RoleRepositoryInterface;

/**
 * Servicio de autenticación
 */
class AuthenticationService
{
    private ?User $currentUser = null;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RoleRepositoryInterface $roleRepository,
    ) {
    }

    /**
     * Autentica un usuario con email y contraseña
     */
    public function authenticate(string $email, string $password): bool
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$user->isActivo()) {
            return false;
        }

        if (!password_verify($password, $user->getPassword())) {
            return false;
        }

        // Actualizar último login
        $user->updateLastLogin();
        $this->userRepository->update($user);

        $this->currentUser = $user;
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
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

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
     * Verifica si el usuario actual tiene un permiso específico
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->currentUser) {
            return false;
        }
        return $this->currentUser->hasPermission($permission);
    }

    /**
     * Obtiene todos los permisos del usuario actual
     */
    public function getCurrentUserPermissions(): array
    {
        if (!$this->currentUser) {
            return [];
        }

        $permissions = [];
        foreach ($this->currentUser->getRoles() as $role) {
            $permissions = array_merge($permissions, $role->getPermissions());
        }

        return array_unique($permissions);
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logout(): void
    {
        $this->currentUser = null;
    }

    /**
     * Cambia la contraseña de un usuario
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): bool
    {
        $user = $this->userRepository->findById($userId);

        if (!$user || !password_verify($oldPassword, $user->getPassword())) {
            return false;
        }

        $user->setPassword(password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]));
        return $this->userRepository->update($user);
    }

    /**
     * Reinicia la contraseña de un usuario (admin)
     */
    public function resetPassword(int $userId, string $newPassword): bool
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            return false;
        }

        $user->setPassword(password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]));
        return $this->userRepository->update($user);
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
        return $this->userRepository->findByEmail($email) !== null;
    }
}
