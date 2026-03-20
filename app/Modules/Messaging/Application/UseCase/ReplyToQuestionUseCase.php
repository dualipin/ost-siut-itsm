<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Messaging\Domain\Entity\Message;
use App\Modules\Messaging\Domain\Entity\MessageAttachment;
use App\Modules\Messaging\Domain\Enum\ThreadStatus;
use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Modules\Messaging\Domain\Exception\ReplyValidationException;
use App\Modules\Messaging\Domain\Repository\MessageAttachmentRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;
use App\Modules\Messaging\Domain\Service\ReplyNotifierInterface;
use App\Modules\Messaging\Infrastructure\Upload\MessageAttachmentUploader;

use function trim;

final readonly class ReplyToQuestionUseCase
{
    public function __construct(
        private MessageThreadRepositoryInterface $threadRepository,
        private MessageRepositoryInterface $messageRepository,
        private MessageAttachmentRepositoryInterface $attachmentRepository,
        private MessageAttachmentUploader $attachmentUploader,
        private ReplyNotifierInterface $replyNotifier,
        private TransactionManager $transactionManager,
    ) {}

    /**
     * @param array<int, array{tmp_name: string, name: string, size: int}> $attachments
     * @throws ReplyValidationException
     */
    public function execute(
        int $threadId,
        int $adminUserId,
        string $replyBody,
        array $attachments = [],
    ): void {
        $cleanBody = trim($replyBody);

        if ($cleanBody === '') {
            throw ReplyValidationException::emptyBody();
        }

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

        if ($thread->externalEmail === null || trim($thread->externalEmail) === '') {
            throw ReplyValidationException::missingExternalEmail($threadId);
        }

        // Obtener el primer mensaje (la pregunta original) para incluirla en el email
        $messages = $this->messageRepository->findByThreadId($threadId);
        $originalQuestion = $messages !== [] ? $messages[0]->body : '(Sin pregunta registrada)';

        $this->transactionManager->transactional(function () use (
            $threadId,
            $adminUserId,
            $cleanBody,
            $attachments,
        ): void {
            $messageId = $this->messageRepository->create(
                new Message(
                    id: null,
                    threadId: $threadId,
                    body: $cleanBody,
                    senderId: $adminUserId,
                ),
            );

            // Procesar adjuntos
            foreach ($attachments as $fileData) {
                if (!isset($fileData['tmp_name']) || $fileData['tmp_name'] === '') {
                    continue;
                }

                $attachment = $this->attachmentUploader->upload(
                    $fileData['tmp_name'],
                    $fileData['name'],
                    (int) $fileData['size'],
                );

                $fullAttachment = new MessageAttachment(
                    id: null,
                    messageId: $messageId,
                    filePath: $attachment->filePath,
                    fileName: $attachment->fileName,
                    mimeType: $attachment->mimeType,
                    fileSize: $attachment->fileSize,
                );

                $this->attachmentRepository->create($fullAttachment);
            }

            $this->threadRepository->updateStatus($threadId, ThreadStatus::Answered);
            $this->threadRepository->updateAssignedTo($threadId, $adminUserId);
        });

        $this->replyNotifier->notifyQuestionReply(
            toEmail: $thread->externalEmail,
            toName: $thread->externalName ?? 'Usuario',
            question: $originalQuestion,
            replyBody: $cleanBody,
        );
    }
}
