<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Messaging\Domain\Entity\Message;
use App\Modules\Messaging\Domain\Entity\MessageThread;
use App\Modules\Messaging\Domain\Entity\MessageAttachment;
use App\Modules\Messaging\Domain\Enum\ThreadStatus;
use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Modules\Messaging\Domain\Enum\ThreadVisibility;
use App\Modules\Messaging\Domain\Exception\ContactMessageValidationException;
use App\Modules\Messaging\Domain\Repository\MessageAttachmentRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;
use App\Modules\Messaging\Domain\Service\ContactMessageNotifierInterface;
use App\Modules\Messaging\Infrastructure\Upload\MessageAttachmentUploader;

use function filter_var;
use function preg_match;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final readonly class CreateContactMessageUseCase
{
    public function __construct(
        private MessageThreadRepositoryInterface $threadRepository,
        private MessageRepositoryInterface $messageRepository,
        private MessageAttachmentRepositoryInterface $attachmentRepository,
        private MessageAttachmentUploader $attachmentUploader,
        private ContactMessageNotifierInterface $notifier,
        private TransactionManager $transactionManager,
    ) {}

    public function execute(
        string $name,
        string $email,
        ?string $phone,
        ?string $subject,
        string $message,
        array $attachments = [],
        ?int $senderId = null,
    ): int {
        $cleanName = trim($name);
        $cleanEmail = trim($email);
        $cleanMessage = trim($message);
        $cleanPhone = $phone !== null ? trim($phone) : null;
        $cleanSubject = $subject !== null ? trim($subject) : "";

        if ($cleanName === "" || $cleanEmail === "" || $cleanMessage === "") {
            throw ContactMessageValidationException::requiredFields();
        }

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw ContactMessageValidationException::invalidEmail();
        }

        if ($cleanPhone !== null && $cleanPhone !== "" && !preg_match('/^\d{10}$/', $cleanPhone)) {
            throw ContactMessageValidationException::invalidPhone();
        }

        $finalSubject = $cleanSubject !== "" ? $cleanSubject : "Contacto desde formulario web";
        $finalPhone = $cleanPhone !== "" ? $cleanPhone : null;

        return $this->transactionManager->transactional(function () use (
            $cleanName,
            $cleanEmail,
            $finalPhone,
            $finalSubject,
            $cleanMessage,
            $attachments,
            $senderId,
        ): int {
            $thread = new MessageThread(
                id: null,
                threadType: ThreadType::Contact,
                status: ThreadStatus::Pending,
                visibility: ThreadVisibility::Private,
                senderId: $senderId,
                externalName: $cleanName,
                externalEmail: $cleanEmail,
                externalPhone: $finalPhone,
                recipientId: null,
                subject: $finalSubject,
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
                    body: $cleanMessage,
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

                $uploaded = $this->attachmentUploader->upload(
                    $fileData['tmp_name'],
                    $fileData['name'],
                    (int) $fileData['size'],
                );

                $fullAttachment = new MessageAttachment(
                    id: null,
                    messageId: $messageId,
                    filePath: $uploaded->filePath,
                    fileName: $uploaded->fileName,
                    mimeType: $uploaded->mimeType,
                    fileSize: $uploaded->fileSize,
                );

                $this->attachmentRepository->create($fullAttachment);
            }

            $this->notifier->notifyAdminOfNewContact(
                threadId: $threadId,
                name: $cleanName,
                email: $cleanEmail,
                phone: $finalPhone,
                subject: $finalSubject,
                message: $cleanMessage,
            );

            return $threadId;
        });
    }
}
