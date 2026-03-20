<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Repository;

use App\Modules\Messaging\Domain\Entity\Message;

interface MessageRepositoryInterface
{
    public function create(Message $message): int;
}
