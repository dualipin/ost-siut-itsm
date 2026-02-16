<?php

namespace App\Module\Publicacion\Entity;

enum TipoPublicacionEnum: string
{
    case Aviso = "aviso";
    case Noticia = "noticia";
    case Gestion = "gestion";
}
