<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Messaging\Domain\Entity\Message;
use App\Modules\Messaging\Domain\Enum\ThreadStatus;
use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Modules\Messaging\Domain\Exception\ReplyValidationException;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;
use App\Modules\Messaging\Domain\Service\ReplyNotifierInterface;

use function trim;

final readonly class ReplyToContactUseCase
{
    public function __construct(
        private MessageThreadRepositoryInterface $threadRepository,
        private MessageRepositoryInterface $messageRepository,
        private ReplyNotifierInterface $replyNotifier,
        private TransactionManager $transactionManager,
    ) {}

    /**
     * @throws ReplyValidationException
     */
    public function execute(
        int $threadId,
        int $adminUserId,
        string $replyBody,
    ): void {
        $cleanBody = trim($replyBody);

        if ($cleanBody === '') {
            throw ReplyValidationException::emptyBody();
        }

        $thread = $this->threadRepository->findById($threadId);

        if ($thread === null) {
            throw ReplyValidationException::threadNotFound($threadId);
        }

        if ($thread->threadType !== ThreadType::Contact) {
            throw ReplyValidationException::invalidThreadType(
                ThreadType::Contact->value,
                $thread->threadType->value,
            );
        }

        if ($thread->externalEmail === null || trim($thread->externalEmail) === '') {
            throw ReplyValidationException::missingExternalEmail($threadId);
        }

        $this->transactionManager->transactional(function () use (
            $threadId,
            $adminUserId,
            $cleanBody,
        ): void {
            $this->messageRepository->create(
                new Message(
                    id: null,
                    threadId: $threadId,
                    body: $cleanBody,
                    senderId: $adminUserId,
                ),
            );

            $this->threadRepository->updateStatus($threadId, ThreadStatus::Attended);
            $this->threadRepository->updateAssignedTo($threadId, $adminUserId);
        });

        $this->replyNotifier->notifyContactReply(
            toEmail: $thread->externalEmail,
            toName: $thread->externalName ?? 'Usuario',
            subject: $thread->subject ?? 'Contacto',
            replyBody: $cleanBody,
        );
    }
}
