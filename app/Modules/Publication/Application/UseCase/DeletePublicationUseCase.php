<?php

namespace App\Modules\Publication\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Modules\Publication\Domain\Exception\PublicationValidationException;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;
use App\Modules\Publication\Infrastructure\Upload\PublicationAttachmentUploader;

final readonly class DeletePublicationUseCase
{
    public function __construct(
        private PublicationRepositoryInterface $publicationRepository,
        private PublicationAttachmentUploader $attachmentUploader,
        private TransactionManager $transactionManager,
    ) {}

    public function execute(int $publicationId): PublicationTypeEnum
    {
        $publication = $this->publicationRepository->findById($publicationId);

        if ($publication === null) {
            throw new PublicationValidationException(
                "La publicación que intentas eliminar no existe.",
            );
        }

        $pathsToDelete = [];

        if ($publication->thumbnailUrl !== null && $publication->thumbnailUrl !== "") {
            $pathsToDelete[] = $publication->thumbnailUrl;
        }

        foreach ($publication->attachments as $attachment) {
            $pathsToDelete[] = $attachment->filePath;
        }

        $this->transactionManager->transactional(function () use ($publicationId): void {
            $this->publicationRepository->deleteById($publicationId);
        });

        foreach ($pathsToDelete as $path) {
            $this->attachmentUploader->delete($path);
        }

        return $publication->type;
    }
}
