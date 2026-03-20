<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $container = App\Bootstrap::buildContainer();
    echo "✓ Container built successfully\n";
    
    // Test getting the admin dashboard usecase
    $useCase = $container->get(
        \App\Modules\Dashboard\Application\UseCase\GetAdministradorDashboardDataUseCase::class
    );
    echo "✓ AdminDashboardUseCase resolved\n";
    
    // Test the repository directly (without user context for now)
    $repo = $container->get(
        \App\Modules\Dashboard\Domain\Repository\DashboardAdministradorRepositoryInterface::class
    );
    
    // Try to call getLoanKanbanData - this was the failing query
    $kanbanData = $repo->getLoanKanbanData();
    echo "✓ getLoanKanbanData() executed successfully\n";
    echo "  - Returned " . count($kanbanData) . " status groups\n";
    
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "\n✓ All checks passed!\n";
