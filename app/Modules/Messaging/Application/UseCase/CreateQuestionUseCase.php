<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Messaging\Domain\Entity\Message;
use App\Modules\Messaging\Domain\Entity\MessageThread;
use App\Modules\Messaging\Domain\Enum\ThreadStatus;
use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Modules\Messaging\Domain\Enum\ThreadVisibility;
use App\Modules\Messaging\Domain\Exception\QuestionValidationException;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;
use App\Modules\Messaging\Domain\Service\QuestionNotifierInterface;

use function filter_var;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final readonly class CreateQuestionUseCase
{
    public function __construct(
        private MessageThreadRepositoryInterface $threadRepository,
        private MessageRepositoryInterface $messageRepository,
        private QuestionNotifierInterface $notifier,
        private TransactionManager $transactionManager,
    ) {}

    /**
     * @throws QuestionValidationException
     */
    public function execute(
        string $name,
        string $email,
        string $question,
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
        ): int {
            $thread = new MessageThread(
                id: null,
                threadType: ThreadType::QA,
                status: ThreadStatus::Pending,
                visibility: ThreadVisibility::Private,
                senderId: null,
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

            $this->messageRepository->create(
                new Message(
                    id: null,
                    threadId: $threadId,
                    body: $cleanQuestion,
                    senderId: null,
                    sentAt: null,
                    readAt: null,
                    deletedAt: null,
                ),
            );

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
