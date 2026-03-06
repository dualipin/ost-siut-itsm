<?php

namespace App\Module\Mensajeria\Enum;

enum TipoMensajeEnum: string
{
    case ContactoGeneral = "contacto_general";
    case TransparenciaDuda = "transparencia_duda";
    case BuzonQueja = "buzon_queja";
}
