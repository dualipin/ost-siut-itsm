<?php
declare(strict_types=1);

header('HTTP/1.1 404 Not Found');

/* @var Latte\Engine $latte */
$latte = require_once __DIR__ . '/src/latte.php';

$templatePath = __DIR__ . '/plantillas/no-encontrado.latte';

$latte->render($templatePath);
