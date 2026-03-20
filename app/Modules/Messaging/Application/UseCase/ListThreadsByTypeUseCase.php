<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;

final readonly class ListThreadsByTypeUseCase
{
    public function __construct(
        private MessageThreadRepositoryInterface $threadRepository,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function execute(ThreadType $type): array
    {
        return $this->threadRepository->findByType($type);
    }
}
