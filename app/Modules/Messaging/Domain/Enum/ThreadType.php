<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Enum;

enum ThreadType: string
{
    case Contact = 'contact';
    case QA = 'qa';
    case Chat = 'chat';
}
