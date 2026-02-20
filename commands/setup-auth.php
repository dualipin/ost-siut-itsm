<?php

/**
 * Comando para ejecutar migraciones y seeding de autenticación
 * Uso: php commands/setup-auth.php
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Bootstrap;
use App\Infrastructure\Database\MigrationRunner;
use App\Infrastructure\Database\AuthSeeder;

try {
    $container = Bootstrap::buildContainer();

    // Ejecutar migraciones
    echo "=== Ejecutando migraciones ===\n\n";
    $pdo = $container->get(\PDO::class);
    $migrationRunner = new MigrationRunner($pdo);

    $migrationsPath = dirname(__DIR__) . '/migrations';
    if (!is_dir($migrationsPath)) {
        mkdir($migrationsPath, 0755, true);
    }

    $migrationRunner->runAllMigrations($migrationsPath);

    // Ejecutar seeder
    echo "\n=== Ejecutando seeder ===\n\n";
    $seeder = new AuthSeeder(
        $container->get(\App\Module\Auth\Repository\RoleRepositoryInterface::class),
        $container->get(\App\Module\Auth\Repository\UserRepositoryInterface::class),
    );
    $seeder->seed();

    echo "\n✓ Setup de autenticación completado!\n";

} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
