<?php

use App\Module\Prestamo\Util\CalculadoraInteresSimple;

require_once __DIR__ . "/../bootstrap.php";

//CalculadoraInteresSimple::calcular(1000, 0.04, 6);

// Ejemplo de uso:
$resultado = CalculadoraInteresSimple::diasParaProximoCorte();
echo "Faltan " .
    $resultado["dias"] .
    " días para el próximo corte (" .
    $resultado["fecha_corte"] .
    ").";
