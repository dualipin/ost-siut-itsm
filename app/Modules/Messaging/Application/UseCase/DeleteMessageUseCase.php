<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Application\UseCase;

use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use DateTimeImmutable;
use RuntimeException;

final readonly class DeleteMessageUseCase
{
    public function __construct(
        private MessageRepositoryInterface $messageRepository,
    ) {}

    public function execute(int $messageId, int $userId): void
    {
        $message = $this->messageRepository->findById($messageId);

        if ($message === null) {
            throw new RuntimeException("Mensaje no encontrado.");
        }

        if ($message->senderId !== $userId) {
            throw new RuntimeException("No tienes permiso para eliminar este mensaje.");
        }

        $now = new DateTimeImmutable();
        $diff = $now->getTimestamp() - $message->sentAt->getTimestamp();
        $minutes = $diff / 60;

        if ($minutes > 15) {
            throw new RuntimeException("Solo puedes eliminar mensajes dentro de los primeros 15 minutos.");
        }

        $this->messageRepository->softDelete($messageId);
    }
}
