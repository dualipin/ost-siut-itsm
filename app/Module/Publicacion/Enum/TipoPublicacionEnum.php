<?php

namespace App\Module\Publicacion\Enum;

enum TipoPublicacionEnum: string
{
    case Aviso = "aviso";
    case Noticia = "noticia";
    case Gestion = "gestion";
}
