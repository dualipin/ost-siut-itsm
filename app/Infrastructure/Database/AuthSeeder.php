<?php

namespace App\Infrastructure\Database;

use App\Module\Auth\Service\RoleService;
use App\Module\Auth\Service\AuthenticationService;
use App\Module\Auth\Repository\RoleRepositoryInterface;
use App\Module\Auth\Repository\UserRepositoryInterface;

/**
 * Seeder para datos iniciales de autenticación
 */
class AuthSeeder
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Ejecuta el seeder
     */
    public function seed(): void
    {
        echo "Seeding auth tables...\n";

        $this->seedRoles();
        $this->seedUsers();

        echo "Auth seeding completed!\n";
    }

    /**
     * Siembra los roles predefinidos
     */
    private function seedRoles(): void
    {
        echo "Creating roles...\n";

        $predefinedRoles = RoleService::getPredefinedRoles();

        foreach ($predefinedRoles as $roleData) {
            // Verificar si el rol ya existe
            $existingRole = $this->roleRepository->findByName($roleData['name']);

            if ($existingRole) {
                echo "  - Role '{$roleData['name']}' already exists\n";
                continue;
            }

            $roleId = $this->roleRepository->save(
                new \App\Module\Auth\Entity\Role(
                    id: 0,
                    name: $roleData['name'],
                    description: $roleData['description'],
                    permissions: $roleData['permissions'],
                )
            );

            echo "  - Role '{$roleData['name']}' created with ID: $roleId\n";
        }
    }

    /**
     * Siembra usuarios de ejemplo
     */
    private function seedUsers(): void
    {
        echo "Creating users...\n";

        $defaultPassword = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);

        $users = [
            [
                'email' => 'admin@ejemplo.com',
                'nombre' => 'Administrador',
                'apellidos' => 'Sistema',
                'role' => 'admin',
            ],
            [
                'email' => 'gerente@ejemplo.com',
                'nombre' => 'Gerente',
                'apellidos' => 'Finanzas',
                'role' => 'gerente',
            ],
            [
                'email' => 'empleado@ejemplo.com',
                'nombre' => 'Empleado',
                'apellidos' => 'Empresa',
                'role' => 'empleado',
            ],
        ];

        foreach ($users as $userData) {
            // Verificar si el usuario ya existe
            $existingUser = $this->userRepository->findByEmail($userData['email']);

            if ($existingUser) {
                echo "  - User '{$userData['email']}' already exists\n";
                continue;
            }

            $user = new \App\Module\Auth\Entity\User(
                id: 0,
                email: $userData['email'],
                password: $defaultPassword,
                nombre: $userData['nombre'],
                apellidos: $userData['apellidos'],
            );

            $userId = $this->userRepository->save($user);

            // Asignar rol
            $role = $this->roleRepository->findByName($userData['role']);
            if ($role) {
                $this->userRepository->assignRole($userId, $role->getId());
                echo "  - User '{$userData['email']}' created with role '{$userData['role']}'\n";
            }
        }
    }
}
