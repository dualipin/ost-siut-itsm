<?php
declare(strict_types=1);

header('HTTP/1.1 403 Forbidden');

require_once __DIR__ . '/src/configuracion.php';

$latte = \App\Fabricas\FabricaLatte::obtenerInstancia();

$templatePath = __DIR__ . '/plantillas/prohibido.latte';

$latte->render($templatePath);
