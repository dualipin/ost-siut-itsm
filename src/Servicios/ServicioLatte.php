<?php
// src/Servicios/ServicioLatte.php
declare(strict_types=1);

namespace App\Servicios;

use App\Configuracion\Contexto;
use App\Fabricas\FabricaLatte;

final class ServicioLatte
{
    // Método estático para renderizar directamente
    public static function renderizar(string $plantilla, array $parametros = []): void
    {
        $latte = FabricaLatte::obtenerInstancia();

        $parametrosGlobales = [
                'miembro' => Contexto::obtenerMiembro(),
                'csrf_token' => Contexto::obtenerCSRFToken(),
        ];

        $latte->render($plantilla, array_merge($parametrosGlobales, $parametros));
    }
}
