<?php
// src/configuracion.php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Configuracion\VariablesEntorno;
use App\Manejadores\Sesion;
use Dotenv\Dotenv;


$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

Sesion::iniciar();