<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Enum;

enum ThreadStatus: string
{
    // contact
    case Pending = 'pending';
    case Attended = 'attended';

    // qa
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';

    // chat
    case Active = 'active';
    case Archived = 'archived';
}
