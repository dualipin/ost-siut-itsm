<?php

namespace App\Modules\Messaging\Domain\Enum;

enum MessagePriorityEnum: string
{
    case Low = "baja";
    case Medium = "media";
    case High = "alta";
    case Urgent = "urgente";
}
