<?php

namespace App\Module\Auth\Repository;

use App\Module\Auth\Entity\Role;

/**
 * Interfaz para repositorio de roles
 */
interface RoleRepositoryInterface
{
    /**
     * Buscar rol por ID
     */
    public function findById(int $id): ?Role;

    /**
     * Buscar rol por nombre
     */
    public function findByName(string $name): ?Role;

    /**
     * Traer todos los roles
     */
    public function findAll(): array;

    /**
     * Guardar un rol
     */
    public function save(Role $role): int;

    /**
     * Actualizar un rol
     */
    public function update(Role $role): bool;

    /**
     * Eliminar un rol
     */
    public function delete(int $id): bool;

    /**
     * Obtener roles de un usuario
     */
    public function findByUserId(int $userId): array;

    /**
     * Asignar permiso a rol
     */
    public function assignPermission(int $roleId, string $permission): bool;

    /**
     * Remover permiso de rol
     */
    public function removePermission(int $roleId, string $permission): bool;

    /**
     * Obtener todos los permisos
     */
    public function getAllPermissions(): array;
}
