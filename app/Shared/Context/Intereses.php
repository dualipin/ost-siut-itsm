<?php

namespace App\Shared\Context;

enum Intereses: string
{
    case AHORRADOR_NO_AGREMIADO = '3.0';
    case AHORRADOR_AGREMIADO = '2.0';
    case NO_AHORRADOR_AGREMIADO = '2.5';

    /**
     * Obtiene el valor numérico del tipo de interés.
     */
    public function valor(): float
    {
        return (float) $this->value;
    }
}

