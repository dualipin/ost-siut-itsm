<?php

use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();

ServicioLatte::renderizar(__DIR__ . '/solicitudes-prestamos.latte');