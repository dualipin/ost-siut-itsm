<?php
declare(strict_types=1);


use Latte\Engine;

require_once __DIR__ . '/../vendor/autoload.php';


$latte = new Engine;

$templatePath = __DIR__ . '/../plantillas/cuenta/recuperar-contra.latte';

$params = [
    // 'title' => 'Página de ejemplo',
];

$latte->render($templatePath, $params);
