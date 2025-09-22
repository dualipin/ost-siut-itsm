<?php
// src/configuracion.php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Configuracion\VariablesEntorno;
use App\Manejadores\Sesion;


VariablesEntorno::init();
Sesion::iniciar();