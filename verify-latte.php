<?php
require_once __DIR__ . '/vendor/autoload.php';

use Latte\Engine;

$latte = new Engine();

$files = [
    'templates/dashboards/dashboard-agremiado.latte',
    'templates/dashboards/dashboard-lider.latte',
    'templates/dashboards/dashboard-finanzas.latte',
    'templates/dashboards/dashboard-no-agremiado.latte'
];

$success = true;
foreach ($files as $file) {
    echo "Checking $file... ";
    try {
        $latte->compile(__DIR__ . '/' . $file);
        echo "OK\n";
    } catch (\Throwable $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        $success = false;
    }
}

exit($success ? 0 : 1);
