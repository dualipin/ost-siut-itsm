<?php

declare(strict_types=1);

namespace App\Modules\Requests\Application\UseCase;

use App\Modules\Requests\Domain\Entity\Request;
use App\Modules\Requests\Domain\Entity\RequestAttachment;
use App\Modules\Requests\Domain\Enum\RequestStatusEnum;
use App\Modules\Requests\Domain\Repository\RequestRepositoryInterface;
use App\Modules\Requests\Domain\Repository\RequestAttachmentRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CreateRequestUseCase
{
    public function __construct(
        private RequestRepositoryInterface $requestRepository,
        private RequestAttachmentRepositoryInterface $attachmentRepository,
    ) {
    }

    /**
     * @param array<array{path: string, mime: string, description: string}> $files
     */
    public function execute(int $userId, int $requestTypeId, string $reason, array $files = []): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('El motivo de la solicitud no puede estar vacío.');
        }

        $folio = $this->requestRepository->nextFolio();
        $now   = new DateTimeImmutable();

        $request = new Request(
            requestId:     0,
            userId:        $userId,
            requestTypeId: $requestTypeId,
            folio:         $folio,
            reason:        $reason,
            status:        RequestStatusEnum::PENDIENTE,
            adminNotes:    null,
            resolvedBy:    null,
            resolvedAt:    null,
            createdAt:     $now,
        );

        $requestId = $this->requestRepository->save($request);

        // Record initial status history
        $this->requestRepository->saveStatusHistory(
            requestId:  $requestId,
            changedBy:  $userId,
            statusFrom: null,
            statusTo:   RequestStatusEnum::PENDIENTE->value,
            notes:      'Solicitud creada.',
        );

        // Save attachments
        foreach ($files as $file) {
            $attachment = new RequestAttachment(
                attachmentId: 0,
                requestId:    $requestId,
                filePath:     $file['path'],
                mimeType:     $file['mime'] ?? null,
                description:  $file['description'] ?? null,
                uploadedAt:   $now,
            );
            $this->attachmentRepository->save($attachment);
        }

        return $folio;
    }
}
