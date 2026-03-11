<?php

namespace App\Modules\Publication\Domain\Enum;

enum PublicationAttachmentTypeEnum: string
{
    case Image = "image";
    case Document = "document";
}
