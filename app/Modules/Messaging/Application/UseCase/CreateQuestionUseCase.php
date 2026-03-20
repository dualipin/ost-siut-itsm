<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Messaging\Domain\Entity\Message;
use App\Modules\Messaging\Domain\Entity\MessageAttachment;
use App\Modules\Messaging\Domain\Entity\MessageThread;
use App\Modules\Messaging\Domain\Enum\ThreadStatus;
use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Modules\Messaging\Domain\Enum\ThreadVisibility;
use App\Modules\Messaging\Domain\Exception\QuestionValidationException;
use App\Modules\Messaging\Domain\Repository\MessageAttachmentRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;
use App\Modules\Messaging\Domain\Service\QuestionNotifierInterface;
use App\Modules\Messaging\Infrastructure\Upload\MessageAttachmentUploader;

use function filter_var;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final readonly class CreateQuestionUseCase
{
    public function __construct(
        private MessageThreadRepositoryInterface $threadRepository,
        private MessageRepositoryInterface $messageRepository,
        private MessageAttachmentRepositoryInterface $attachmentRepository,
        private MessageAttachmentUploader $attachmentUploader,
        private QuestionNotifierInterface $notifier,
        private TransactionManager $transactionManager,
    ) {}

    /**
     * @param array<int, array{tmp_name: string, name: string, size: int}> $attachments
     * @throws QuestionValidationException
     */
    public function execute(
        string $name,
        string $email,
        string $question,
        array $attachments = [],
        ?int $senderId = null,
    ): int {
        $cleanName = trim($name);
        $cleanEmail = trim($email);
        $cleanQuestion = trim($question);

        if ($cleanName === "" || $cleanEmail === "" || $cleanQuestion === "") {
            throw QuestionValidationException::requiredFields();
        }

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw QuestionValidationException::invalidEmail();
        }

        return $this->transactionManager->transactional(function () use (
            $cleanName,
            $cleanEmail,
            $cleanQuestion,
            $attachments,
            $senderId,
        ): int {
            $thread = new MessageThread(
                id: null,
                threadType: ThreadType::QA,
                status: ThreadStatus::Pending,
                visibility: ThreadVisibility::Private,
                senderId: $senderId,
                externalName: $cleanName,
                externalEmail: $cleanEmail,
                externalPhone: null,
                recipientId: null,
                subject: "Duda sobre Transparencia",
                assignedTo: null,
                externalChannel: null,
                createdAt: null,
                updatedAt: null,
                deletedAt: null,
            );

            $threadId = $this->threadRepository->create($thread);

            $messageId = $this->messageRepository->create(
                new Message(
                    id: null,
                    threadId: $threadId,
                    body: $cleanQuestion,
                    senderId: $senderId,
                    sentAt: null,
                    readAt: null,
                    deletedAt: null,
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

            $this->notifier->notifyAdminOfNewQuestion(
                threadId: $threadId,
                name: $cleanName,
                email: $cleanEmail,
                question: $cleanQuestion,
            );

            return $threadId;
        });
    }
}
