<?php

namespace App\Module\Mensajeria\Enum;

enum PrioridadMensajeEnum: string
{
    case Baja = "baja";
    case Media = "media";
    case Alta = "alta";
    case Urgente = "urgente";
}
