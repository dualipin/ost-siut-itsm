<?php

namespace App\Modules\Messaging\Domain\Enum;

enum MessageStateEnum: string
{
    case Open = "abierto";
    case InProgress = "en_proceso";
    case Closed = "cerrado";
    case Answered = "respondido";
    case Archived = "archivado";
}
