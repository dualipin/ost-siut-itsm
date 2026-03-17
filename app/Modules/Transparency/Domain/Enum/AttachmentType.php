<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Domain\Enum;

enum AttachmentType: string
{
    case PDF = 'PDF';
    case IMAGEN = 'IMAGEN';
    case ENLACE = 'ENLACE';
    case OTRO = 'OTRO';
}
