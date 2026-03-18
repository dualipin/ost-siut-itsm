<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Transparency\Domain\Entity\TransparencyAttachment;
use App\Modules\Transparency\Domain\Enum\AttachmentType;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;
use App\Modules\Transparency\Domain\Repository\FileStorageInterface;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

final readonly class AddAttachmentUseCase
{
    public function __construct(
        private TransparencyRepositoryInterface $repository,
        private FileStorageInterface $fileStorage,
        private TransactionManager $transactionManager
    ) {
    }

    public function execute(
        int $transparencyId,
        string $sourcePath,
        string $originalFilename,
        string $mimeType,
        string $attachmentTypeValue,
        ?string $description
    ): TransparencyAttachment {
        $transparency = $this->repository->findById($transparencyId);
        if ($transparency === null) {
            throw TransparencyNotFoundException::withId($transparencyId);
        }

        $type = AttachmentType::tryFrom($attachmentTypeValue);
        if ($type === null) {
            throw new InvalidArgumentException("Tipo de adjunto inválido: {$attachmentTypeValue}");
        }

        // Caso especial para ENLACE (No se guarda archivo)
        if ($type === AttachmentType::ENLACE) {
            $attachment = new TransparencyAttachment(
                id: null,
                transparencyId: $transparencyId,
                filePath: $sourcePath, // En este caso sourcePath es la URL
                mimeType: 'text/uri-list',
                attachmentType: $type,
                description: $description
            );
            return $this->repository->saveAttachment($attachment);
        }

        return $this->transactionManager->transactional(function () use (
            $transparency,
            $transparencyId,
            $sourcePath,
            $originalFilename,
            $mimeType,
            $type,
            $description
        ) {
            try {
                $savedPath = $this->fileStorage->store($sourcePath, $originalFilename, $transparency->isPrivate);
                
                $attachment = new TransparencyAttachment(
                    id: null,
                    transparencyId: $transparencyId,
                    filePath: $savedPath,
                    mimeType: $mimeType,
                    attachmentType: $type,
                    description: $description
                );

                return $this->repository->saveAttachment($attachment);
            } catch (RuntimeException $e) {
                throw new RuntimeException("Error al procesar el archivo adjunto: " . $e->getMessage(), 0, $e);
            }
        });
    }
}
