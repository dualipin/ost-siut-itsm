<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Enum;

enum ThreadVisibility: string
{
    case Private = 'private';
    case Public = 'public';
}
