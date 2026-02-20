<?php

namespace App\Module\Auth\Service;

use App\Module\Auth\Entity\Role;
use App\Module\Auth\Repository\RoleRepositoryInterface;

/**
 * Servicio para gestión de roles y permisos
 */
class RoleService
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
    ) {
    }

    /**
     * Obtiene todos los roles
     */
    public function getAllRoles(): array
    {
        return $this->roleRepository->findAll();
    }

    /**
     * Obtiene un rol por ID
     */
    public function getRoleById(int $id): ?Role
    {
        return $this->roleRepository->findById($id);
    }

    /**
     * Obtiene un rol por nombre
     */
    public function getRoleByName(string $name): ?Role
    {
        return $this->roleRepository->findByName($name);
    }

    /**
     * Crea un nuevo rol
     */
    public function createRole(
        string $name,
        string $description,
        array $permissions = [],
    ): int {
        $role = new Role(
            id: 0,
            name: $name,
            description: $description,
            permissions: $permissions,
        );

        return $this->roleRepository->save($role);
    }

    /**
     * Actualiza un rol existente
     */
    public function updateRole(int $id, string $name, string $description, array $permissions = []): bool
    {
        $role = $this->roleRepository->findById($id);

        if (!$role) {
            return false;
        }

        $role->setName($name);
        $role->setDescription($description);
        $role->setPermissions($permissions);
        $role->setUpdatedAt(new \DateTimeImmutable());

        return $this->roleRepository->update($role);
    }

    /**
     * Elimina un rol
     */
    public function deleteRole(int $id): bool
    {
        return $this->roleRepository->delete($id);
    }

    /**
     * Asigna un permiso a un rol
     */
    public function assignPermissionToRole(int $roleId, string $permission): bool
    {
        return $this->roleRepository->assignPermission($roleId, $permission);
    }

    /**
     * Remueve un permiso de un rol
     */
    public function removePermissionFromRole(int $roleId, string $permission): bool
    {
        return $this->roleRepository->removePermission($roleId, $permission);
    }

    /**
     * Obtiene todos los permisos disponibles
     */
    public function getAllPermissions(): array
    {
        return $this->roleRepository->getAllPermissions();
    }

    /**
     * Obtiene permisos predefinidos del sistema
     */
    public static function getPredefinedPermissions(): array
    {
        return [
            // Gestión de usuarios
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
            'usuarios.cambiar-contraseña',

            // Gestión de roles
            'roles.ver',
            'roles.crear',
            'roles.editar',
            'roles.eliminar',

            // Gestión de préstamos
            'prestamos.ver',
            'prestamos.crear',
            'prestamos.editar',
            'prestamos.eliminar',
            'prestamos.aprobar',
            'prestamos.rechazar',

            // Gestión de finanzas
            'finanzas.ver',
            'finanzas.reportes',
            'finanzas.exportar',

            // Gestión de transparencia
            'transparencia.ver',
            'transparencia.crear',
            'transparencia.editar',

            // Sistema
            'sistema.administracion',
            'sistema.logs',
            'sistema.configuracion',
        ];
    }

    /**
     * Obtiene roles predefinidos
     */
    public static function getPredefinedRoles(): array
    {
        return [
            [
                'name' => 'admin',
                'description' => 'Administrador del sistema con acceso completo',
                'permissions' => self::getPredefinedPermissions(),
            ],
            [
                'name' => 'gerente',
                'description' => 'Gerente con acceso a gestión de préstamos y reportes',
                'permissions' => [
                    'prestamos.ver',
                    'prestamos.crear',
                    'prestamos.editar',
                    'prestamos.aprobar',
                    'finanzas.ver',
                    'finanzas.reportes',
                    'usuarios.ver',
                ],
            ],
            [
                'name' => 'empleado',
                'description' => 'Empleado con acceso a vista de préstamos',
                'permissions' => [
                    'prestamos.ver',
                ],
            ],
            [
                'name' => 'usuario',
                'description' => 'Usuario estándar del sistema',
                'permissions' => [],
            ],
        ];
    }
}
