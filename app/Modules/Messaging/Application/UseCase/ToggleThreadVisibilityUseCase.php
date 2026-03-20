<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Modules\Messaging\Domain\Enum\ThreadVisibility;
use App\Modules\Messaging\Domain\Exception\ReplyValidationException;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;

final readonly class ToggleThreadVisibilityUseCase
{
    public function __construct(
        private MessageThreadRepositoryInterface $threadRepository,
    ) {}

    /**
     * @throws ReplyValidationException
     */
    public function execute(int $threadId, string $visibilityStr): void
    {
        $thread = $this->threadRepository->findById($threadId);

        if ($thread === null) {
            throw ReplyValidationException::threadNotFound($threadId);
        }

        if ($thread->threadType !== ThreadType::QA) {
            throw ReplyValidationException::invalidThreadType(
                ThreadType::QA->value,
                $thread->threadType->value,
            );
        }

        $visibility = ThreadVisibility::from($visibilityStr);
        $this->threadRepository->updateVisibility($threadId, $visibility);
    }
}
