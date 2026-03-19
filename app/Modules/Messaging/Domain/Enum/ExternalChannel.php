<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Enum;

enum ExternalChannel: string
{
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case InPerson = 'in_person';
    case Email = 'email';
}
