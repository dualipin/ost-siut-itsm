<?php
declare(strict_types=1);

/* @var Latte\Engine $latte */
$latte = require_once __DIR__ . '/src/latte.php';

$templatePath = __DIR__ . '/plantillas/transparencia.latte';

$latte->render($templatePath);
