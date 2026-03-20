<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Repository;

use App\Modules\Messaging\Domain\Entity\MessageThread;
use App\Modules\Messaging\Domain\Enum\ThreadStatus;
use App\Modules\Messaging\Domain\Enum\ThreadType;

interface MessageThreadRepositoryInterface
{
    public function create(MessageThread $thread): int;

    /** @return array<int, array<string, mixed>> */
    public function findByType(ThreadType $type): array;

    public function findById(int $id): ?MessageThread;

    public function updateStatus(int $id, ThreadStatus $status): void;

    public function updateAssignedTo(int $id, int $userId): void;
}
