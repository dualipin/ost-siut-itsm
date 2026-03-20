<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $container = App\Bootstrap::buildContainer();
    echo "✓ Container built successfully\n";
    
    // Verify dashboard services
    $services = [
        'GetLiderDashboardDataUseCase' => \App\Modules\Dashboard\Application\UseCase\GetLiderDashboardDataUseCase::class,
        'GetAdministradorDashboardDataUseCase' => \App\Modules\Dashboard\Application\UseCase\GetAdministradorDashboardDataUseCase::class,
        'GetFinanzasDashboardDataUseCase' => \App\Modules\Dashboard\Application\UseCase\GetFinanzasDashboardDataUseCase::class,
        'GetAgremiadoDashboardDataUseCase' => \App\Modules\Dashboard\Application\UseCase\GetAgremiadoDashboardDataUseCase::class,
        'GetPublicDashboardDataUseCase' => \App\Modules\Dashboard\Application\UseCase\GetPublicDashboardDataUseCase::class,
        'AlertEvaluationService' => \App\Modules\Dashboard\Application\Service\AlertEvaluationService::class,
    ];
    
    foreach ($services as $name => $class) {
        try {
            $service = $container->get($class);
            echo "✓ $name resolved\n";
        } catch (Throwable $e) {
            echo "✗ $name failed: " . $e->getMessage() . "\n";
        }
    }
} catch (Throwable $e) {
    echo "✗ Error building container: " . $e->getMessage() . "\n";
    exit(1);
}
