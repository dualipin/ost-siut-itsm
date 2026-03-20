<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Modules\Messaging\Domain\Entity\Message;
use App\Modules\Messaging\Domain\Entity\MessageThread;
use App\Modules\Messaging\Domain\Exception\ReplyValidationException;
use App\Modules\Messaging\Domain\Repository\MessageAttachmentRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;

final readonly class GetThreadDetailUseCase
{
    public function __construct(
        private MessageThreadRepositoryInterface $threadRepository,
        private MessageRepositoryInterface $messageRepository,
        private MessageAttachmentRepositoryInterface $attachmentRepository,
    ) {}

    /**
     * @return array{thread: MessageThread, messages: array<int, array{message: Message, attachments: MessageAttachment[]}>}
     * @throws ReplyValidationException
     */
    public function execute(int $threadId): array
    {
        $thread = $this->threadRepository->findById($threadId);

        if ($thread === null) {
            throw ReplyValidationException::threadNotFound($threadId);
        }

        $messages = $this->messageRepository->findByThreadId($threadId);
        
        $messagesWithAttachments = [];
        foreach ($messages as $msg) {
            $messagesWithAttachments[] = [
                'message' => $msg,
                'attachments' => $this->attachmentRepository->findByMessageId($msg->id),
            ];
        }

        return [
            'thread' => $thread,
            'messages' => $messagesWithAttachments,
        ];
    }
}
