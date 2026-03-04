<?php

use App\Module\Prestamo\Util\CalculadoraInteresSimple;

it("verificar calculo de interés simple", function () {
    CalculadoraInteresSimple::calcular(1000, 0.04, 6);
});
