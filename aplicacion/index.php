<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/configuracion.php';

\App\Manejadores\SesionProtegida::proteger();
\App\Servicios\ServicioLatte::renderizar(__DIR__ . '/plantillas/index.latte');
