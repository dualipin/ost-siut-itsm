<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Repository;

use App\Modules\Messaging\Domain\Entity\MessageThread;
use App\Modules\Messaging\Domain\Enum\ThreadStatus;
use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Modules\Messaging\Domain\Enum\ThreadVisibility;

interface MessageThreadRepositoryInterface
{
    public function create(MessageThread $thread): int;

    /** @return array<int, array<string, mixed>> */
    public function findByType(ThreadType $type, ?int $month = null, ?int $year = null): array;

    public function findById(int $id): ?MessageThread;

    public function updateStatus(int $id, ThreadStatus $status): void;

    public function updateVisibility(int $id, ThreadVisibility $visibility): void;

    public function updateAssignedTo(int $id, int $userId): void;

    /** @return array<int, array<string, mixed>> */
    public function findBySenderId(int $senderId, ThreadType $type): array;

    /** @return array<int, array<string, mixed>> */
    public function findPublicAnsweredByType(ThreadType $type): array;
}
