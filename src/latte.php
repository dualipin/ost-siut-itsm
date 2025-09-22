<?php
// src/latte.php
declare(strict_types=1);

require_once __DIR__ . '/configuracion.php';

$latte = new Latte\Engine;

$latte->setTempDirectory(__DIR__ . '/../temp/latte');
$latte->setAutoRefresh(($_ENV['APP_ENV'] ?? 'prod') === 'dev');

return $latte;