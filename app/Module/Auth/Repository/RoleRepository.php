<?php

namespace App\Module\Auth\Repository;

use App\Module\Auth\Entity\Role;
use PDO;

/**
 * Implementación de repositorio de roles
 */
class RoleRepository implements RoleRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?Role
    {
        $stmt = $this->pdo->prepare('SELECT * FROM roles WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return $this->mapToRole($data);
    }

    public function findByName(string $name): ?Role
    {
        $stmt = $this->pdo->prepare('SELECT * FROM roles WHERE name = ?');
        $stmt->execute([$name]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return $this->mapToRole($data);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM roles ORDER BY name ASC');
        $roles = [];

        foreach ($stmt->fetchAll() as $data) {
            $roles[] = $this->mapToRole($data);
        }

        return $roles;
    }

    public function save(Role $role): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO roles (name, description, created_at) VALUES (?, ?, ?)'
        );

        $stmt->execute([
            $role->getName(),
            $role->getDescription(),
            $role->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        $roleId = (int)$this->pdo->lastInsertId();

        // Guardar permisos
        foreach ($role->getPermissions() as $permission) {
            $this->assignPermission($roleId, $permission);
        }

        return $roleId;
    }

    public function update(Role $role): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE roles SET name = ?, description = ?, updated_at = ? WHERE id = ?'
        );

        if (!$stmt->execute([
            $role->getName(),
            $role->getDescription(),
            new \DateTime(),
            $role->getId(),
        ])) {
            return false;
        }

        // Actualizar permisos
        $this->pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')
            ->execute([$role->getId()]);

        foreach ($role->getPermissions() as $permission) {
            $this->assignPermission($role->getId(), $permission);
        }

        return true;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM roles WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.* FROM roles r 
             INNER JOIN user_roles ur ON r.id = ur.role_id 
             WHERE ur.user_id = ?'
        );
        $stmt->execute([$userId]);

        $roles = [];
        foreach ($stmt->fetchAll() as $data) {
            $roles[] = $this->mapToRole($data);
        }

        return $roles;
    }

    public function assignPermission(int $roleId, string $permission): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO role_permissions (role_id, permission) VALUES (?, ?)'
        );
        return $stmt->execute([$roleId, $permission]);
    }

    public function removePermission(int $roleId, string $permission): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM role_permissions WHERE role_id = ? AND permission = ?'
        );
        return $stmt->execute([$roleId, $permission]);
    }

    public function getAllPermissions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT permission FROM role_permissions ORDER BY permission ASC'
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function mapToRole(array $data): Role
    {
        $stmt = $this->pdo->prepare(
            'SELECT permission FROM role_permissions WHERE role_id = ? ORDER BY permission'
        );
        $stmt->execute([$data['id']]);
        
        $permissions = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return new Role(
            id: (int)$data['id'],
            name: $data['name'],
            description: $data['description'],
            permissions: $permissions,
            createdAt: new \DateTimeImmutable($data['created_at']),
            updatedAt: $data['updated_at'] ? new \DateTimeImmutable($data['updated_at']) : null,
        );
    }
}
