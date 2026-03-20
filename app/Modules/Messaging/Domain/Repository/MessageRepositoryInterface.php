<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Repository;

use App\Modules\Messaging\Domain\Entity\Message;

interface MessageRepositoryInterface
{
    public function create(Message $message): int;

    public function findById(int $id): ?Message;

    /** @return Message[] */
    public function findByThreadId(int $threadId): array;

    public function softDelete(int $id): void;
}
