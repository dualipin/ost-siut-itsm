<?php

namespace App\Module\Mensajeria\Enum;

enum EstadoMensajeEnum: string
{
    case Abierto = "abierto";
    case EnProceso = "en_proceso";
    case Cerrado = "cerrado";
    case Respondido = "respondido";
    case Archivado = "archivado";
}
