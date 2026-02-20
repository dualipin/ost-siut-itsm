<?php

namespace App\Infrastructure\Database;

use PDO;

/**
 * Gestor de migraciones para la base de datos
 */
class MigrationRunner
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Ejecuta todas las migraciones
     */
    public function runAllMigrations(string $migrationsPath): void
    {
        $files = array_filter(
            scandir($migrationsPath),
            fn($file) => str_ends_with($file, '.sql')
        );

        sort($files);

        foreach ($files as $file) {
            $this->runMigration($migrationsPath . '/' . $file);
        }
    }

    /**
     * Ejecuta una migración individual
     */
    public function runMigration(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Migration file not found: $filePath");
        }

        $sql = file_get_contents($filePath);

        // Dividir por punto y coma, pero ignorar los comentarios
        $statements = array_filter(
            array_map('trim', preg_split('/;(?=\s*(?:--|\/\*|$))/m', $sql)),
            fn($statement) => !empty($statement) && !str_starts_with($statement, '--')
        );

        foreach ($statements as $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (\PDOException $e) {
                throw new \Exception(
                    "Error executing migration $filePath: " . $e->getMessage()
                );
            }
        }

        echo "Migration executed: $filePath\n";
    }

    /**
     * Crea una tabla de migraciones para rastrear las ejecutadas
     */
    public function createMigrationsTable(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    /**
     * Registra una migración como ejecutada
     */
    public function recordMigration(string $migrationName): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO migrations (migration) VALUES (?)'
        );
        $stmt->execute([$migrationName]);
    }

    /**
     * Obtiene las migraciones ejecutadas
     */
    public function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query(
            'SELECT migration FROM migrations ORDER BY executed_at'
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Verifica si una migración ha sido ejecutada
     */
    public function isMigrationExecuted(string $migrationName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM migrations WHERE migration = ?'
        );
        $stmt->execute([$migrationName]);
        return (bool)$stmt->fetchColumn();
    }
}
