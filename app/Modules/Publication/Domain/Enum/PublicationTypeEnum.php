<?php

namespace App\Modules\Publication\Domain\Enum;

enum PublicationTypeEnum: string
{
    case Alert = "aviso";
    case News = "noticia";
    case Management = "gestion";
    case Contracts = "contratos";
}
