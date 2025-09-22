<?php
declare(strict_types=1);

use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../src/configuracion.php';

SesionProtegida::proteger();
ServicioLatte::renderizar(__DIR__ . '/buzon.latte');
