<?php

namespace App\Module\Auth\Repository;

use App\Module\Auth\Entity\User;
use App\Module\Auth\Entity\Role;
use PDO;

/**
 * Implementación de repositorio de usuarios
 */
class UserRepository implements UserRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return $this->mapToUser($data);
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return $this->mapToUser($data);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM users ORDER BY nombre ASC');
        $users = [];

        foreach ($stmt->fetchAll() as $data) {
            $users[] = $this->mapToUser($data);
        }

        return $users;
    }

    public function save(User $user): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password, nombre, apellidos, activo, created_at) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $user->getEmail(),
            $user->getPassword(),
            $user->getNombre(),
            $user->getApellidos(),
            (int)$user->isActivo(),
            $user->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(User $user): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET email = ?, password = ?, nombre = ?, apellidos = ?, activo = ?, updated_at = ? 
             WHERE id = ?'
        );

        return $stmt->execute([
            $user->getEmail(),
            $user->getPassword(),
            $user->getNombre(),
            $user->getApellidos(),
            (int)$user->isActivo(),
            new \DateTime(),
            $user->getId(),
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function count(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM users');
        return (int)$stmt->fetch()['count'];
    }

    public function assignRole(int $userId, int $roleId): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)'
        );
        return $stmt->execute([$userId, $roleId]);
    }

    public function removeRole(int $userId, int $roleId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?'
        );
        return $stmt->execute([$userId, $roleId]);
    }

    private function mapToUser(array $data): User
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.* FROM roles r 
             INNER JOIN user_roles ur ON r.id = ur.role_id 
             WHERE ur.user_id = ?'
        );
        $stmt->execute([$data['id']]);
        
        $roles = [];
        foreach ($stmt->fetchAll() as $roleData) {
            $roles[] = $this->mapToRole($roleData);
        }

        return new User(
            id: (int)$data['id'],
            email: $data['email'],
            password: $data['password'],
            nombre: $data['nombre'],
            apellidos: $data['apellidos'],
            roles: $roles,
            activo: (bool)$data['activo'],
            createdAt: new \DateTimeImmutable($data['created_at']),
            updatedAt: $data['updated_at'] ? new \DateTimeImmutable($data['updated_at']) : null,
            lastLogin: $data['last_login'] ? new \DateTimeImmutable($data['last_login']) : null,
        );
    }

    private function mapToRole(array $data): Role
    {
        $stmt = $this->pdo->prepare(
            'SELECT permission FROM role_permissions WHERE role_id = ?'
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
