<?php

namespace App\Module\Auth\Repository;

use App\Module\Auth\Entity\User;

/**
 * Interfaz para repositorio de usuarios
 */
interface UserRepositoryInterface
{
    /**
     * Buscar usuario por ID
     */
    public function findById(int $id): ?User;

    /**
     * Buscar usuario por email
     */
    public function findByEmail(string $email): ?User;

    /**
     * Traer todos los usuarios
     */
    public function findAll(): array;

    /**
     * Guardar un usuario
     */
    public function save(User $user): int;

    /**
     * Actualizar un usuario
     */
    public function update(User $user): bool;

    /**
     * Eliminar un usuario
     */
    public function delete(int $id): bool;

    /**
     * Contar total de usuarios
     */
    public function count(): int;

    /**
     * Asignar rol a usuario
     */
    public function assignRole(int $userId, int $roleId): bool;

    /**
     * Remover rol de usuario
     */
    public function removeRole(int $userId, int $roleId): bool;
}
