<?php

namespace App\Shared\Utils;
use function count;
use function round;

class Numero
{
    /**
     * @param int $numero
     * @return string
     */
    public static function formatearNumero(int $numero): string
    {
        if ($numero < 1000) {
            return $numero;
        }

        $sufijos = ["", "K", "M", "B", "T"];
        $sufijoIndex = 0;

        while ($numero >= 1000 && $sufijoIndex < count($sufijos) - 1) {
            $numero /= 1000;
            $sufijoIndex++;
        }

        return round($numero, 1) . $sufijos[$sufijoIndex];
    }
}
