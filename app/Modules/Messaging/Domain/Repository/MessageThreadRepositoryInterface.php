<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Repository;

use App\Modules\Messaging\Domain\Entity\MessageThread;

interface MessageThreadRepositoryInterface
{
    public function create(MessageThread $thread): int;
}
