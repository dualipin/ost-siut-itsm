<?php
require_once __DIR__ . '/vendor/autoload.php';
use Latte\Engine;
$latte = new Engine();
try {
    $latte->compile(__DIR__ . '/templates/dashboards/dashboard-no-agremiado.latte');
    echo "OK\n";
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
