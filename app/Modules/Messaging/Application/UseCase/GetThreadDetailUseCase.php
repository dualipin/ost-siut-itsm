<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Modules\Messaging\Domain\Entity\Message;
use App\Modules\Messaging\Domain\Entity\MessageThread;
use App\Modules\Messaging\Domain\Exception\ReplyValidationException;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;

final readonly class GetThreadDetailUseCase
{
    public function __construct(
        private MessageThreadRepositoryInterface $threadRepository,
        private MessageRepositoryInterface $messageRepository,
    ) {}

    /**
     * @return array{thread: MessageThread, messages: Message[]}
     * @throws ReplyValidationException
     */
    public function execute(int $threadId): array
    {
        $thread = $this->threadRepository->findById($threadId);

        if ($thread === null) {
            throw ReplyValidationException::threadNotFound($threadId);
        }

        $messages = $this->messageRepository->findByThreadId($threadId);

        return [
            'thread' => $thread,
            'messages' => $messages,
        ];
    }
}
